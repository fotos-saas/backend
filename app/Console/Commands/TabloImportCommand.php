<?php

namespace App\Console\Commands;

use App\Enums\TabloProjectStatus;
use App\Models\TabloContact;
use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import Tablo data from legacy MySQL SQL dump files.
 */
class TabloImportCommand extends Command
{
    protected $signature = 'tablo:import
        {path : Path to the directory containing SQL files}
        {--dry-run : Preview import without saving}
        {--force : Skip confirmation}';

    protected $description = 'Import Tablo data from legacy SQL dump files';

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = $this->option('dry-run');

        if (! is_dir($path)) {
            $this->error("Directory not found: {$path}");

            return 1;
        }

        $this->info('🔄 Tabló adatok importálása...');
        $this->newLine();

        // Find SQL files
        $files = [
            'schools' => $this->findFile($path, 'schools'),
            'partners' => $this->findFile($path, 'partners'),
            'projects' => $this->findFile($path, 'projects'),
            'contacts' => $this->findFile($path, 'contact_people (1)'),
            'junction' => $this->findFile($path, 'contact_people_project'),
        ];

        foreach ($files as $key => $file) {
            if (! $file) {
                $this->warn("  ⚠ Missing SQL file for: {$key}");
            } else {
                $this->line("  ✓ Found: ".basename($file));
            }
        }

        // Schools is now required
        if (! $files['schools']) {
            $this->error('Schools SQL file is required!');

            return 1;
        }

        $this->newLine();

        // Parse SQL files
        $this->info('📖 SQL fájlok feldolgozása...');
        $schools = $this->parseSqlInserts(file_get_contents($files['schools']), 'schools');
        $partners = $files['partners'] ? $this->parseSqlInserts(file_get_contents($files['partners']), 'partners') : [];
        $projects = $files['projects'] ? $this->parseSqlInserts(file_get_contents($files['projects']), 'projects') : [];
        $contacts = $files['contacts'] ? $this->parseSqlInserts(file_get_contents($files['contacts']), 'contact_people') : [];
        $junction = $files['junction'] ? $this->parseSqlInserts(file_get_contents($files['junction']), 'contact_people_project') : [];

        $this->line("  Schools: ".count($schools));
        $this->line("  Partners: ".count($partners));
        $this->line("  Projects: ".count($projects));
        $this->line("  Contacts: ".count($contacts));
        $this->line("  Junction: ".count($junction));
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY-RUN mód - nem történik mentés');
            $this->showPreview($schools, $partners, $projects, $contacts);

            return 0;
        }

        if (! $this->option('force') && ! $this->confirm('Folytatod az importálást?')) {
            $this->info('Import megszakítva.');

            return 0;
        }

        // Import data
        DB::beginTransaction();

        try {
            $schoolIdMap = $this->importSchools($schools);
            $partnerIdMap = $this->importPartners($partners);
            $projectIdMap = $this->importProjects($projects, $partnerIdMap, $schoolIdMap, $schools);
            $this->importContacts($contacts, $junction, $projectIdMap);

            DB::commit();

            $this->newLine();
            $this->info('✅ Import sikeres!');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Import hiba: '.$e->getMessage());

            return 1;
        }
    }

    private function findFile(string $path, string $name): ?string
    {
        $files = glob($path.'/'.$name.'*.sql');

        return $files[0] ?? null;
    }

    private function parseSqlInserts(string $sql, string $tableName): array
    {
        $results = [];

        // Find column names from INSERT statement
        if (! preg_match("/INSERT INTO `{$tableName}` \(([^)]+)\)/", $sql, $columnMatch)) {
            return $results;
        }

        $columns = array_map(function ($col) {
            return trim($col, '` ');
        }, explode(',', $columnMatch[1]));

        // Find all VALUES
        preg_match_all('/\((\d+,\s*(?:\'(?:[^\'\\\\]|\\\\.)*\'|NULL|[^,)]+)(?:,\s*(?:\'(?:[^\'\\\\]|\\\\.)*\'|NULL|[^,)]+))*)\)/', $sql, $matches);

        foreach ($matches[0] as $valueGroup) {
            $values = $this->parseValueGroup($valueGroup);
            if (count($values) === count($columns)) {
                $results[] = array_combine($columns, $values);
            }
        }

        return $results;
    }

    private function parseValueGroup(string $group): array
    {
        $group = trim($group, '()');
        $values = [];
        $current = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($group); $i++) {
            $char = $group[$i];

            if ($escape) {
                $current .= $char;
                $escape = false;

                continue;
            }

            if ($char === '\\') {
                $escape = true;
                $current .= $char;

                continue;
            }

            if ($char === "'" && ! $escape) {
                $inString = ! $inString;
                $current .= $char;

                continue;
            }

            if ($char === ',' && ! $inString) {
                $values[] = $this->cleanValue(trim($current));
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $values[] = $this->cleanValue(trim($current));
        }

        return $values;
    }

    private function cleanValue(string $value): ?string
    {
        if ($value === 'NULL') {
            return null;
        }

        // Remove surrounding quotes and unescape
        if (preg_match("/^'(.*)'$/s", $value, $match)) {
            $value = $match[1];
            $value = str_replace(["\\\\", "\\'", "\\r", "\\n"], ['\\', "'", "\r", "\n"], $value);
        }

        return $value;
    }

    private function importSchools(array $schools): array
    {
        $this->info('📥 Iskolák importálása...');
        $idMap = [];

        foreach ($schools as $school) {
            $oldId = $school['id'];

            $new = TabloSchool::create([
                'local_id' => (string) $oldId,
                'name' => $school['name'],
                'city' => $school['city'],
            ]);

            $idMap[$oldId] = $new->id;
        }

        $this->line("  ✓ ".count($schools)." iskola importálva");

        return $idMap;
    }

    private function importPartners(array $partners): array
    {
        $this->info('📥 Partnerek importálása...');
        $idMap = [];

        foreach ($partners as $partner) {
            $oldId = $partner['id'];

            $new = TabloPartner::create([
                'name' => $partner['name'],
                'slug' => $partner['slug'],
                'email' => $partner['email'],
            ]);

            $idMap[$oldId] = $new->id;
            $this->line("  ✓ {$partner['name']}");
        }

        return $idMap;
    }

    private function importProjects(array $projects, array $partnerIdMap, array $schoolIdMap, array $schools): array
    {
        $this->info('📥 Projektek importálása...');
        $idMap = [];
        $count = 0;

        // Build school lookup by old ID for getting names
        $schoolLookup = [];
        foreach ($schools as $school) {
            $schoolLookup[$school['id']] = $school;
        }

        foreach ($projects as $project) {
            $partnerId = $partnerIdMap[$project['partner_id']] ?? null;

            if (! $partnerId) {
                $this->warn("  ⚠ Partner not found for project {$project['id']}, skipping");

                continue;
            }

            // Get school info
            $oldSchoolId = $project['school_id'];
            $newSchoolId = $schoolIdMap[$oldSchoolId] ?? null;
            $schoolName = $schoolLookup[$oldSchoolId]['name'] ?? 'Ismeretlen iskola';

            // Build project name: School Name - Class Name Class Year
            $className = trim($project['class_name'] ?? '');
            $classYear = trim($project['class_year'] ?? '');
            $name = $schoolName.' - '.$className.($classYear ? ' '.$classYear : '');

            // ALL projects start as NotStarted
            $status = TabloProjectStatus::NotStarted;

            // Store extra data in JSON
            $data = [
                'uuid' => $project['uuid'],
                'size_id' => $project['size_id'],
                'class_name' => $project['class_name'],
                'class_year' => $project['class_year'],
                'teacher_description' => $project['teacher_description'],
                'student_description' => $project['student_description'],
                'quote' => $project['quote'],
                'description' => $project['description'],
                'font_family' => $project['font_family'],
                'color' => $project['color'],
                'other_file' => $project['other_file'],
                'background' => $project['background'],
                'order_form' => $project['order_form'],
                'contact_replied_at' => $project['contact_replied_at'],
                'our_replied_at' => $project['our_replied_at'],
                'category' => $project['category'],
                'ai_category' => $project['ai_category'],
                'ai_category_score' => $project['ai_category_score'],
                'sort_type' => $project['sort_type'],
                'old_school_id' => $project['school_id'],
                'old_status_id' => $project['status_id'],
            ];

            $new = TabloProject::create([
                'partner_id' => $partnerId,
                'school_id' => $newSchoolId,
                'local_id' => (string) $project['id'],
                'external_id' => (string) $project['id'],
                'name' => $name,
                'status' => $status,
                'is_aware' => false,
                'data' => $data,
                'sync_at' => $project['sync_at'] ? now()->parse($project['sync_at']) : null,
                'created_at' => $project['created_at'] ? now()->parse($project['created_at']) : now(),
                'updated_at' => $project['updated_at'] ? now()->parse($project['updated_at']) : now(),
            ]);

            $idMap[$project['id']] = $new->id;
            $count++;

            if ($count % 20 === 0) {
                $this->line("  ... {$count} projekt importálva");
            }
        }

        $this->line("  ✓ {$count} projekt importálva");

        return $idMap;
    }

    private function importContacts(array $contacts, array $junction, array $projectIdMap): void
    {
        $this->info('📥 Kapcsolattartók importálása...');

        // Build contact lookup by old ID
        $contactLookup = [];
        foreach ($contacts as $contact) {
            $contactLookup[$contact['id']] = $contact;
        }

        $count = 0;

        foreach ($junction as $link) {
            $oldProjectId = $link['project_id'];
            $oldContactId = $link['contact_people_id'];

            $newProjectId = $projectIdMap[$oldProjectId] ?? null;
            $contact = $contactLookup[$oldContactId] ?? null;

            if (! $newProjectId || ! $contact) {
                continue;
            }

            TabloContact::create([
                'tablo_project_id' => $newProjectId,
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'note' => $contact['comment'],
            ]);

            $count++;
        }

        $this->line("  ✓ {$count} kapcsolattartó importálva");
    }

    private function showPreview(array $schools, array $partners, array $projects, array $contacts): void
    {
        // Build school lookup
        $schoolLookup = [];
        foreach ($schools as $school) {
            $schoolLookup[$school['id']] = $school;
        }

        $this->newLine();
        $this->info('📋 Iskolák (első 5):');
        foreach (array_slice($schools, 0, 5) as $s) {
            $this->line("  - {$s['name']} ({$s['city']})");
        }

        $this->newLine();
        $this->info('📋 Partnerek (első 3):');
        foreach (array_slice($partners, 0, 3) as $p) {
            $this->line("  - {$p['name']} ({$p['email']})");
        }

        $this->newLine();
        $this->info('📋 Projektek (első 5):');
        foreach (array_slice($projects, 0, 5) as $p) {
            $schoolName = $schoolLookup[$p['school_id']]['name'] ?? 'Ismeretlen';
            $this->line("  - {$schoolName} - {$p['class_name']} {$p['class_year']}");
        }

        $this->newLine();
        $this->info('📋 Kapcsolattartók (első 5):');
        foreach (array_slice($contacts, 0, 5) as $c) {
            $this->line("  - {$c['name']} ({$c['email']})");
        }
    }
}
