<?php

namespace App\Console\Commands;

use App\Models\TabloContact;
use App\Models\TabloOrderAnalysis;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Services\ClaudeService;
use App\Services\TabloApiService;
use Illuminate\Console\Command;

class SyncProjectFromApiCommand extends Command
{
    protected $signature = 'tablo:sync-from-api
                            {project_id? : Live API projekt ID}
                            {--all : Minden projektet szinkronizál ami rendelkezik external_id-val}
                            {--dry-run : Csak kiírja mit csinálna}
                            {--with-names : AI névsor elemzéssel (ha nincs strukturált)}';

    protected $description = 'Projekt adatok szinkronizálása a live API-ból (api.tablokiraly.hu)';

    public function handle(TabloApiService $apiService, ClaudeService $claudeService): int
    {
        $projectId = $this->argument('project_id');
        $syncAll = $this->option('all');
        $dryRun = $this->option('dry-run');
        $withNames = $this->option('with-names');

        if ($dryRun) {
            $this->warn('DRY-RUN mód - nem történik tényleges módosítás!');
        }

        if ($syncAll) {
            return $this->syncAllProjects($apiService, $claudeService, $dryRun, $withNames);
        }

        if ($projectId) {
            return $this->syncSingleProject((int) $projectId, $apiService, $claudeService, $dryRun, $withNames);
        }

        $this->error('Add meg a projekt ID-t vagy használd a --all opciót!');
        $this->line('Példa: php artisan tablo:sync-from-api 36');
        $this->line('       php artisan tablo:sync-from-api --all');

        return Command::FAILURE;
    }

    private function syncSingleProject(
        int $liveProjectId,
        TabloApiService $apiService,
        ClaudeService $claudeService,
        bool $dryRun,
        bool $withNames
    ): int {
        $this->info("Projekt lekérése az API-ból: #{$liveProjectId}");

        $data = $apiService->getProjectDetails($liveProjectId);

        if (!$data) {
            $this->error('Projekt nem található vagy API hiba!');

            return Command::FAILURE;
        }

        $summary = $apiService->extractProjectSummary($data);
        $this->displayProjectInfo($data, $summary);

        if ($dryRun) {
            $this->warn('DRY-RUN: Nem történt mentés.');

            return Command::SUCCESS;
        }

        // Projekt keresése/létrehozása
        $project = $this->findOrCreateProject($data, $summary);

        // Elemzés frissítése
        $this->updateAnalysis($project, $data, $summary, $apiService, $claudeService, $withNames);

        $this->info("Projekt szinkronizálva: {$project->display_name}");

        return Command::SUCCESS;
    }

    private function syncAllProjects(
        TabloApiService $apiService,
        ClaudeService $claudeService,
        bool $dryRun,
        bool $withNames
    ): int {
        $this->info('Összes projekt szinkronizálása...');

        // Projektek ahol van external_id (azaz a live API-ból jöttek)
        $projects = TabloProject::whereNotNull('external_id')->get();

        $this->info("Találtam {$projects->count()} projektet external_id-val.");

        $bar = $this->output->createProgressBar($projects->count());
        $bar->start();

        $synced = 0;
        $errors = 0;

        foreach ($projects as $project) {
            try {
                $result = $this->syncSingleProjectSilent(
                    (int) $project->external_id,
                    $project,
                    $apiService,
                    $claudeService,
                    $dryRun,
                    $withNames
                );

                if ($result) {
                    $synced++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Hiba #{$project->external_id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Szinkronizálva: {$synced}");
        if ($errors > 0) {
            $this->warn("Hibák: {$errors}");
        }

        return Command::SUCCESS;
    }

    private function syncSingleProjectSilent(
        int $liveProjectId,
        TabloProject $existingProject,
        TabloApiService $apiService,
        ClaudeService $claudeService,
        bool $dryRun,
        bool $withNames
    ): bool {
        $data = $apiService->getProjectDetails($liveProjectId);

        if (!$data) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $summary = $apiService->extractProjectSummary($data);
        $this->updateAnalysis($existingProject, $data, $summary, $apiService, $claudeService, $withNames);

        return true;
    }

    private function displayProjectInfo(array $data, array $summary): void
    {
        $this->table(
            ['Mező', 'Érték'],
            [
                ['Iskola', $summary['school_name'] ?? '-'],
                ['Osztály', $summary['class_name'] ?? '-'],
                ['Év', $summary['class_year'] ?? '-'],
                ['Kontakt', $summary['contact_name'] ?? '-'],
                ['Email', $summary['contact_email'] ?? '-'],
                ['Telefon', $summary['contact_phone'] ?? '-'],
                ['Diákok', $summary['student_count']],
                ['Tanárok', $summary['teacher_count']],
                ['Megrendelőlap', $summary['has_order_form'] ? 'Van' : 'Nincs'],
                ['Háttérkép', $summary['has_background'] ? 'Van' : 'Nincs'],
            ]
        );

        // Első 5 diák
        if (!empty($data['students'])) {
            $this->info('Első 5 diák:');
            foreach (array_slice($data['students'], 0, 5) as $student) {
                $this->line("  - {$student['name']}");
            }
            if (count($data['students']) > 5) {
                $this->line('  ... és még ' . (count($data['students']) - 5) . ' diák');
            }
        }

        // Első 5 tanár
        if (!empty($data['teachers'])) {
            $this->info('Első 5 tanár:');
            foreach (array_slice($data['teachers'], 0, 5) as $teacher) {
                $title = $teacher['title'] ?? 'tanár';
                $this->line("  - {$teacher['name']} ({$title})");
            }
            if (count($data['teachers']) > 5) {
                $this->line('  ... és még ' . (count($data['teachers']) - 5) . ' tanár');
            }
        }
    }

    private function findOrCreateProject(array $data, array $summary): TabloProject
    {
        // Keresés external_id alapján
        $project = TabloProject::where('external_id', $data['id'])->first();

        if ($project) {
            $this->info('Meglévő projekt frissítése...');

            return $project;
        }

        // Iskola keresése/létrehozása
        $school = null;
        if (!empty($summary['school_name'])) {
            $school = TabloSchool::firstOrCreate(
                ['name' => $summary['school_name']],
                ['city' => $summary['school_city'] ?? null]
            );
        }

        // Új projekt
        $project = TabloProject::create([
            'external_id' => $data['id'],
            'school_id' => $school?->id,
            'class_name' => $summary['class_name'],
            'class_year' => $summary['class_year'],
            'status' => 'new',
            'data' => [
                'synced_from_api' => true,
                'api_uuid' => $data['uuid'] ?? null,
            ],
        ]);

        $this->info('Új projekt létrehozva: #' . $project->id);

        // Kontakt létrehozása
        if (!empty($summary['contact_name']) || !empty($summary['contact_email'])) {
            TabloContact::create([
                'tablo_project_id' => $project->id,
                'name' => $summary['contact_name'],
                'email' => $summary['contact_email'],
                'phone' => $summary['contact_phone'],
                'role' => 'megrendelő',
            ]);
        }

        return $project;
    }

    private function updateAnalysis(
        TabloProject $project,
        array $data,
        array $summary,
        TabloApiService $apiService,
        ClaudeService $claudeService,
        bool $withNames
    ): void {
        // Elemzés keresése vagy létrehozása
        $analysis = TabloOrderAnalysis::firstOrCreate(
            ['tablo_project_id' => $project->id],
            ['status' => 'processing']
        );

        // MINDIG a nyers szövegből dolgozunk AI-val (--with-names flag szükséges)
        $students = [];
        $teachers = [];

        if ($withNames) {
            // Diák névsor feldolgozás
            if (!empty($data['student_description'])) {
                $this->line('  AI névsor feldolgozás: diákok...');
                $students = $apiService->parseNameListWithAI(
                    $data['student_description'],
                    'students',
                    $claudeService
                );
                $this->info("    → {$this->countNames($students)} diák feldolgozva");
            }

            // Tanár névsor feldolgozás
            if (!empty($data['teacher_description'])) {
                $this->line('  AI névsor feldolgozás: tanárok...');
                $teachers = $apiService->parseNameListWithAI(
                    $data['teacher_description'],
                    'teachers',
                    $claudeService
                );
                $this->info("    → {$this->countNames($teachers)} tanár feldolgozva");
            }
        } else {
            $this->warn('  Névsor feldolgozás kihagyva (használd a --with-names opciót)');
        }

        // Elemzés frissítése
        $analysis->update([
            'status' => 'completed',
            'analyzed_at' => now(),

            // Kinyert adatok
            'contact_name' => $summary['contact_name'],
            'contact_phone' => $summary['contact_phone'],
            'contact_email' => $summary['contact_email'],
            'school_name' => $summary['school_name'],
            'class_name' => $summary['class_name'],

            'student_count' => count($students),
            'teacher_count' => count($teachers),

            // Design
            'tablo_size' => $data['design']['size'] ?? null,
            'font_style' => $summary['font_family'] ?? $data['font_family'] ?? null,
            'color_scheme' => $summary['color'] ?? $data['color'] ?? null,
            'special_notes' => $summary['description'] ?? $data['description'] ?? null,

            // Teljes adat JSON-ban
            'analysis_data' => [
                'source' => 'live_api',
                'api_project_id' => $data['id'],
                'contact' => $data['contact'] ?? [],
                'school' => $data['school'] ?? [],
                'class' => [
                    'name' => $data['class_name'] ?? null,
                    'year' => $data['class_year'] ?? null,
                ],
                'design' => [
                    'color' => $data['color'] ?? null,
                    'font' => $data['font_family'] ?? null,
                    'quote' => $data['quote'] ?? null,
                    'notes' => $data['description'] ?? null,
                ],
                // Nyers szöveg mezők (eredeti)
                'raw_student_description' => $data['student_description'] ?? null,
                'raw_teacher_description' => $data['teacher_description'] ?? null,
                // AI által feldolgozott nevek
                'students' => $students,
                'teachers' => $teachers,
                'files' => $data['files'] ?? [],
            ],

            'tags' => $this->generateTags($data),
            'warnings' => [],
        ]);
    }

    private function generateTags(array $data): array
    {
        $tags = [];

        // Méret alapján
        if (!empty($data['design']['size'])) {
            $tags[] = $data['design']['size'];
        }

        // Leírás alapján
        $description = strtolower($data['description'] ?? '');

        if (str_contains($description, 'modern') || str_contains($description, 'minimal')) {
            $tags[] = 'modern';
        }
        if (str_contains($description, 'klasszikus') || str_contains($description, 'elegáns')) {
            $tags[] = 'klasszikus';
        }
        if (str_contains($description, 'spotify')) {
            $tags[] = 'spotify';
        }
        if (str_contains($description, 'mesés') || str_contains($description, 'mese')) {
            $tags[] = 'mesés';
        }
        if (str_contains($description, 'karakteres') || str_contains($description, 'karakter')) {
            $tags[] = 'karakteres';
        }

        return array_unique($tags);
    }

    /**
     * Nevek számolása a feldolgozott tömbből.
     */
    private function countNames(array $names): int
    {
        return count($names);
    }
}
