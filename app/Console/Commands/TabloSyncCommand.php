<?php

namespace App\Console\Commands;

use App\Enums\TabloProjectStatus;
use App\Models\TabloContact;
use App\Models\TabloPartner;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Models\TabloSchool;
use App\Models\TeacherArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TabloSyncCommand extends Command
{
    private const API_BASE = 'http://api.tablokiraly.prod/migration/projects';
    private const LEGACY_PARTNER_ID = 3;
    private const NEW_PARTNER_ID = 24;

    protected $signature = 'tablo:sync
        {--fresh : Teljes ujraimport (TORLI a korabbi szinkronizalt adatokat!)}
        {--dry-run : Csak kiirja mit csinalna}
        {--force : Megerosites kihagyasa}
        {--download-images : Kepek letoltese is (lassu!)}
        {--images-only : CSAK kepeket tolt le (nem szinkronizal adatokat)}
        {--page= : Csak egy adott oldalt szinkronizal (teszteleshez)}';

    protected $description = 'Szinkronizalas a regi tablokiraly rendszerbol (REST API)';

    private int $newProjects = 0;
    private int $updatedProjects = 0;
    private int $unchangedProjects = 0;
    private int $newSchools = 0;
    private int $newContacts = 0;
    private int $newPersons = 0;
    private int $updatedPersons = 0;
    private int $newTeacherArchive = 0;
    private int $downloadedImages = 0;
    private int $skippedImages = 0;
    private int $failedImages = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fresh = $this->option('fresh');

        $this->info('Tablo szinkronizacio inditasa...');

        // 1. Partner meppeles
        $partner = TabloPartner::find(self::NEW_PARTNER_ID);
        if (! $partner) {
            $this->error('Partner nem talalhato (ID: ' . self::NEW_PARTNER_ID . ')');
            return 1;
        }

        if (! $partner->local_id) {
            if (! $dryRun) {
                $partner->update(['local_id' => (string) self::LEGACY_PARTNER_ID]);
            }
            $this->line('  Partner local_id beallitva: ' . self::LEGACY_PARTNER_ID);
        }

        $this->info("Partner: {$partner->name} (ID: {$partner->id}, legacy: " . self::LEGACY_PARTNER_ID . ')');
        $this->info('API: ' . self::API_BASE . '?partner_id=' . self::LEGACY_PARTNER_ID);
        $this->newLine();

        // 2. Csak kepek letoltese mod
        if ($this->option('images-only')) {
            return $this->handleImagesOnly($partner);
        }

        // 3. Fresh mod
        if ($fresh) {
            return $this->handleFreshImport($partner, $dryRun);
        }

        // 4. Inkrementalis szinkronizacio
        return $this->handleIncrementalSync($partner, $dryRun);
    }

    private function handleFreshImport(TabloPartner $partner, bool $dryRun): int
    {
        if (! $dryRun && ! $this->option('force')) {
            $this->warn('FIGYELEM: Ez TORLI az osszes szinkronizalt adatot es ujraimportal!');
            if (! $this->confirm('Biztosan folytatod?')) {
                $this->info('Megszakitva.');
                return 0;
            }
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: Fresh import szimulacio');
        }

        if (! $dryRun) {
            DB::beginTransaction();
        }

        try {
            if (! $dryRun) {
                // Persons cascade-del torlodik a projekt torleskol
                $deletedProjects = TabloProject::where('partner_id', $partner->id)
                    ->whereNotNull('external_id')
                    ->delete();
                $this->line("  Torolt projektek: {$deletedProjects}");

                $deletedSchools = TabloSchool::whereNotNull('local_id')
                    ->whereDoesntHave('projects')
                    ->delete();
                $this->line("  Torolt arva iskolak: {$deletedSchools}");
            }

            $result = $this->syncAllPages($partner, $dryRun);

            if (! $dryRun) {
                $this->syncTeacherArchive($partner);
                DB::commit();
            }

            $this->printSummary();
            return $result;
        } catch (\Exception $e) {
            if (! $dryRun) {
                DB::rollBack();
            }
            $this->error('Fresh import hiba: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function handleIncrementalSync(TabloPartner $partner, bool $dryRun): int
    {
        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Inkrementalis szinkronizacio indulna. Folytatod?')) {
                $this->info('Megszakitva.');
                return 0;
            }
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: Szinkronizacio szimulacio');
        }

        $result = $this->syncAllPages($partner, $dryRun);

        if (! $dryRun) {
            $this->syncTeacherArchive($partner);
        }

        $this->printSummary();
        return $result;
    }

    private function syncAllPages(TabloPartner $partner, bool $dryRun): int
    {
        DB::connection()->disableQueryLog();

        $singlePage = $this->option('page');
        $page = $singlePage ? (int) $singlePage : 1;
        $lastPage = $singlePage ? (int) $singlePage : null;

        while (true) {
            $this->newLine();
            $data = $this->fetchPage($page);

            if (! $data) {
                $this->error("API hiba az {$page}. oldalon");
                return 1;
            }

            if ($lastPage === null) {
                $lastPage = $data['last_page'] ?? $page;
            }

            $this->info("Oldal {$page}/{$lastPage} feldolgozasa...");

            $projects = $data['data'] ?? [];

            foreach ($projects as $apiProject) {
                try {
                    $this->syncProject($apiProject, $partner, $dryRun);
                } catch (\Exception $e) {
                    $this->errors++;
                    $this->error("  HIBA projekt #{$apiProject['id']}: " . $e->getMessage());
                    report($e);
                }
            }

            // Memoria felszabaditas oldalankent
            gc_collect_cycles();

            if ($page >= $lastPage || $singlePage) {
                break;
            }

            $page++;
        }

        return 0;
    }

    private function fetchPage(int $page): ?array
    {
        $url = self::API_BASE . '?' . http_build_query([
            'partner_id' => self::LEGACY_PARTNER_ID,
            'page' => $page,
        ]);

        try {
            $response = Http::timeout(30)
                ->retry(3, 500)
                ->get($url);

            if (! $response->successful()) {
                $this->error("API valasz: {$response->status()}");
                return null;
            }

            $data = $response->json();

            if (! is_array($data) || ! isset($data['data'])) {
                $this->error('API valasz nem megfelelo formatumu (hianyzik a data kulcs)');
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->error('API hivas sikertelen: ' . $e->getMessage());
            return null;
        }
    }

    private function syncProject(array $api, TabloPartner $partner, bool $dryRun): void
    {
        $externalId = (string) $api['id'];
        $schoolName = $api['school']['name'] ?? 'Ismeretlen';
        $className = $api['class_name'] ?? '';
        $classYear = $api['class_year'] ?? '';
        $label = "{$schoolName} - {$className}" . ($classYear ? " {$classYear}" : '');

        // 1. Iskola szinkronizalas
        $school = $this->syncSchool($api['school'] ?? null, $partner, $dryRun);

        // 2. Projekt keres external_id alapjan
        $existing = TabloProject::where('external_id', $externalId)->first();

        // Timestamp osszehasonlitas
        $apiUpdatedAt = isset($api['updated_at']) ? Carbon::parse($api['updated_at']) : null;

        if ($existing) {
            // Letezik - frissites szukseges?
            if ($apiUpdatedAt && $existing->updated_at && $apiUpdatedAt->lte($existing->updated_at)) {
                $this->line("  <fg=gray>○ {$label} (valtozatlan)</>");
                $this->unchangedProjects++;
                return;
            }

            // Frissites
            $changes = $this->buildProjectChanges($api, $school);
            $personChanges = 0;

            if (! $dryRun) {
                $existing->update($changes);
                $personChanges = $this->syncPersons($api, $existing, $dryRun);
                $this->syncContact($api, $existing, $partner, $dryRun);
            } else {
                $personChanges = $this->syncPersons($api, $existing, $dryRun);
            }

            $this->line("  <fg=cyan>↻ {$label} (frissitve" . ($personChanges ? ": {$personChanges} person modosult" : '') . ')</>');
            $this->updatedProjects++;
        } else {
            // Uj projekt
            if (! $dryRun) {
                $status = $this->resolveStatus($api);
                $data = $this->buildDataJson($api);

                $project = TabloProject::create([
                    'partner_id' => $partner->id,
                    'school_id' => $school?->id,
                    'external_id' => $externalId,
                    'name' => $label,
                    'class_name' => $className ?: null,
                    'class_year' => $classYear ?: null,
                    'status' => $status,
                    'is_aware' => false,
                    'data' => $data,
                    'created_at' => isset($api['created_at']) ? Carbon::parse($api['created_at']) : now(),
                    'updated_at' => $apiUpdatedAt ?? now(),
                ]);

                $this->syncPersons($api, $project, $dryRun);
                $this->syncContact($api, $project, $partner, $dryRun);

                if ($this->option('download-images')) {
                    $this->downloadProjectImages($api);
                }
            }

            $this->line("  <fg=green>✓ {$label} (uj)</>");
            $this->newProjects++;
        }
    }

    private function syncSchool(?array $apiSchool, TabloPartner $partner, bool $dryRun): ?TabloSchool
    {
        if (! $apiSchool || empty($apiSchool['id'])) {
            return null;
        }

        $localId = (string) $apiSchool['id'];
        $existing = TabloSchool::where('local_id', $localId)->first();

        if ($existing) {
            // Frissites ha nev vagy varos valtozott
            if (! $dryRun) {
                $existing->update([
                    'name' => $apiSchool['name'] ?? $existing->name,
                    'city' => $apiSchool['city'] ?? $existing->city,
                ]);
            }
            return $existing;
        }

        if ($dryRun) {
            $this->newSchools++;
            return null;
        }

        $this->newSchools++;

        $school = TabloSchool::create([
            'local_id' => $localId,
            'name' => $apiSchool['name'] ?? 'Ismeretlen iskola',
            'city' => $apiSchool['city'] ?? null,
        ]);

        // Partner-iskola kapcsolat
        if (! $partner->schools()->where('tablo_schools.id', $school->id)->exists()) {
            $partner->schools()->attach($school->id);
        }

        return $school;
    }

    private function syncPersons(array $api, TabloProject $project, bool $dryRun): int
    {
        $changes = 0;

        // Diakok
        $students = $api['students'] ?? [];
        foreach ($students as $student) {
            $changed = $this->syncPerson($student, $project, 'student', $dryRun);
            if ($changed) {
                $changes++;
            }
        }

        // Tanarok
        $teachers = $api['teachers'] ?? [];
        foreach ($teachers as $teacher) {
            $changed = $this->syncPerson($teacher, $project, 'teacher', $dryRun);
            if ($changed) {
                $changes++;
            }
        }

        return $changes;
    }

    private function syncPerson(array $apiPerson, TabloProject $project, string $type, bool $dryRun): bool
    {
        $localId = (string) $apiPerson['id'];

        // Note osszeallitasa
        $noteParts = [];
        if (! empty($apiPerson['original_name']) && $apiPerson['original_name'] !== ($apiPerson['name'] ?? '')) {
            $noteParts[] = 'Eredeti nev: ' . $apiPerson['original_name'];
        }
        if (! empty($apiPerson['position']) && is_string($apiPerson['position'])) {
            $noteParts[] = $apiPerson['position'];
        }
        $note = implode(' | ', $noteParts) ?: null;

        // Sorrend (position mezo - int)
        $sortPosition = is_numeric($apiPerson['sort_number'] ?? null) ? (int) $apiPerson['sort_number'] : null;

        $existing = TabloPerson::where('tablo_project_id', $project->id)
            ->where('local_id', $localId)
            ->first();

        if ($existing) {
            $changed = false;
            $updates = [];

            if ($existing->name !== ($apiPerson['name'] ?? $existing->name)) {
                $updates['name'] = $apiPerson['name'];
                $changed = true;
            }

            if ($note !== null && $existing->note !== $note) {
                $updates['note'] = $note;
                $changed = true;
            }

            if ($sortPosition !== null && (int) $existing->position !== $sortPosition) {
                $updates['position'] = $sortPosition;
                $changed = true;
            }

            if ($changed && ! $dryRun && ! empty($updates)) {
                $existing->update($updates);
            }

            if ($changed) {
                $this->updatedPersons++;
            }

            return $changed;
        }

        // Uj person
        if (! $dryRun) {
            TabloPerson::create([
                'tablo_project_id' => $project->id,
                'name' => $apiPerson['name'] ?? 'Ismeretlen',
                'type' => $type,
                'local_id' => $localId,
                'note' => $note,
                'position' => $sortPosition,
            ]);
        }

        $this->newPersons++;

        return true;
    }

    private function syncContact(array $api, TabloProject $project, TabloPartner $partner, bool $dryRun): void
    {
        $contact = $api['contact'] ?? null;
        if (! $contact || empty($contact['email'])) {
            return;
        }

        if ($dryRun) {
            return;
        }

        // Partner-szintu firstOrCreate email alapjan
        $tabloContact = TabloContact::firstOrCreate(
            [
                'partner_id' => $partner->id,
                'email' => $contact['email'],
            ],
            [
                'name' => $contact['name'] ?? '',
                'phone' => $contact['phone'] ?? null,
            ]
        );

        if ($tabloContact->wasRecentlyCreated) {
            $this->newContacts++;
        }

        // Frissites ha van ujabb adat
        if (! empty($contact['name']) && $tabloContact->name !== $contact['name']) {
            $tabloContact->update(['name' => $contact['name']]);
        }
        if (! empty($contact['phone']) && $tabloContact->phone !== $contact['phone']) {
            $tabloContact->update(['phone' => $contact['phone']]);
        }

        // Pivot: is_primary
        if (! $project->contacts()->where('tablo_contacts.id', $tabloContact->id)->exists()) {
            $project->contacts()->attach($tabloContact->id, ['is_primary' => true]);
        }
    }

    private function buildProjectChanges(array $api, ?TabloSchool $school): array
    {
        $changes = [
            'status' => $this->resolveStatus($api),
            'data' => $this->buildDataJson($api),
        ];

        if ($school) {
            $changes['school_id'] = $school->id;
        }

        if (! empty($api['class_name'])) {
            $changes['class_name'] = $api['class_name'];
        }

        if (! empty($api['class_year'])) {
            $changes['class_year'] = $api['class_year'];
        }

        return $changes;
    }

    private function buildDataJson(array $api): array
    {
        $data = [];

        $jsonFields = ['quote', 'font_family', 'color', 'description', 'tags', 'sample_url'];

        foreach ($jsonFields as $field) {
            if (isset($api[$field]) && $api[$field] !== null && $api[$field] !== '') {
                $data[$field] = $api[$field];
            }
        }

        // Extra mezok amik nem illeszkednek a fo tablara
        $extraFields = [
            'uuid', 'size_id', 'teacher_description', 'student_description',
            'other_file', 'background', 'order_form', 'category',
            'ai_category', 'ai_category_score', 'sort_type',
            'contact_replied_at', 'our_replied_at',
        ];

        foreach ($extraFields as $field) {
            if (isset($api[$field]) && $api[$field] !== null && $api[$field] !== '') {
                $data[$field] = $api[$field];
            }
        }

        return $data;
    }

    private function resolveStatus(array $api): TabloProjectStatus
    {
        $statusId = $api['status']['id'] ?? $api['status_id'] ?? null;

        if ($statusId !== null) {
            $status = TabloProjectStatus::fromLegacyId((int) $statusId);
            if ($status) {
                return $status;
            }
        }

        return TabloProjectStatus::NotStarted;
    }

    private function handleImagesOnly(TabloPartner $partner): int
    {
        DB::connection()->disableQueryLog();

        $singlePage = $this->option('page');
        $page = $singlePage ? (int) $singlePage : 1;
        $lastPage = $singlePage ? (int) $singlePage : null;

        $baseDir = storage_path('app/migration_images');
        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $this->info('Kepek letoltese indul...');
        $this->info("Cel mappa: {$baseDir}");

        while (true) {
            $this->newLine();
            $data = $this->fetchPage($page);

            if (! $data) {
                $this->error("API hiba az {$page}. oldalon");
                return 1;
            }

            if ($lastPage === null) {
                $lastPage = $data['last_page'] ?? $page;
            }

            $this->info("Oldal {$page}/{$lastPage} - kepek letoltese...");

            foreach ($data['data'] ?? [] as $apiProject) {
                $this->downloadProjectImages($apiProject);
            }

            gc_collect_cycles();

            if ($page >= $lastPage || $singlePage) {
                break;
            }

            $page++;
        }

        $this->newLine();
        $this->info('Kep letoltes osszesites:');
        $this->line("  Letoltott: {$this->downloadedImages}");
        $this->line("  Mar letezett (skip): {$this->skippedImages}");
        $this->line("  Sikertelen: {$this->failedImages}");
        $this->newLine();
        $this->info('Kesz.');

        return 0;
    }

    private function downloadProjectImages(array $api): void
    {
        $externalId = (string) $api['id'];
        $projectDir = storage_path("app/migration_images/{$externalId}");

        // 1. Tablo minta (sample)
        $sampleUrl = $api['sample_url'] ?? null;
        if ($sampleUrl && filter_var($sampleUrl, FILTER_VALIDATE_URL)) {
            $ext = pathinfo(parse_url($sampleUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $sampleFile = "{$projectDir}/sample.{$ext}";

            if (file_exists($sampleFile)) {
                $this->skippedImages++;
            } else {
                if (! is_dir($projectDir)) {
                    mkdir($projectDir, 0755, true);
                }
                $this->downloadFile($sampleUrl, $sampleFile, "sample (projekt #{$externalId})");
            }
        }

        // 2. Tanar kepek
        foreach ($api['teachers'] ?? [] as $teacher) {
            $imageData = $teacher['selected_image'] ?? null;
            if (! $imageData || ! is_array($imageData) || empty($imageData['url'])) {
                continue;
            }

            $imageUrl = $imageData['url'];
            $fileName = $imageData['file_name'] ?? Str::slug($teacher['name'] ?? $teacher['id'], '_') . '.jpg';

            $safeName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME), '_');
            $ext = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'jpg';

            $teacherDir = "{$projectDir}/teachers";
            $targetFile = "{$teacherDir}/{$safeName}_{$teacher['id']}.{$ext}";

            if (file_exists($targetFile)) {
                $this->skippedImages++;
                continue;
            }

            if (! is_dir($teacherDir)) {
                mkdir($teacherDir, 0755, true);
            }

            $this->downloadFile($imageUrl, $targetFile, $teacher['name'] ?? "teacher #{$teacher['id']}");
        }
    }

    private function downloadFile(string $url, string $targetPath, string $label): void
    {
        try {
            $response = Http::timeout(30)->retry(2, 1000)->get($url);

            if (! $response->successful()) {
                $this->failedImages++;
                $this->warn("    ✗ {$label}: HTTP {$response->status()}");
                return;
            }

            file_put_contents($targetPath, $response->body());
            $size = round(filesize($targetPath) / 1024);
            $this->downloadedImages++;

            // Csak minden 50. kepnel irjunk ki progresszt
            if ($this->downloadedImages % 50 === 0) {
                $this->line("  ... {$this->downloadedImages} kep letoltve (skip: {$this->skippedImages}, hiba: {$this->failedImages})");
            }
        } catch (\Exception $e) {
            $this->failedImages++;
            $this->warn("    ✗ {$label}: {$e->getMessage()}");
        }
    }

    private function syncTeacherArchive(TabloPartner $partner): void
    {
        $this->newLine();
        $this->info('Tanar adatbazis szinkronizalasa (teacher_archive)...');

        // Egyedi tanarok: nev + iskola kombinaciora csoportositva
        $teachers = DB::table('tablo_persons as tp')
            ->join('tablo_projects as tpr', 'tpr.id', '=', 'tp.tablo_project_id')
            ->where('tp.type', 'teacher')
            ->where('tpr.partner_id', $partner->id)
            ->select(
                'tp.name',
                'tpr.school_id',
                DB::raw("MAX(tp.note) as note"),
                DB::raw("MAX(tp.position) as position_val")
            )
            ->groupBy('tp.name', 'tpr.school_id')
            ->get();

        foreach ($teachers as $teacher) {
            if (empty($teacher->name)) {
                continue;
            }

            // Nev szetvalasztasa: "Dr. Kovacs Janos" -> title_prefix="Dr.", canonical_name="Kovacs Janos"
            $titlePrefix = null;
            $canonicalName = trim($teacher->name);

            $prefixes = ['Dr.', 'Prof.', 'dr.', 'prof.', 'Id.', 'Ifj.', 'id.', 'ifj.', 'özv.', 'Özv.'];
            foreach ($prefixes as $prefix) {
                if (str_starts_with($canonicalName, $prefix . ' ')) {
                    $titlePrefix = $prefix;
                    $canonicalName = trim(substr($canonicalName, strlen($prefix)));
                    break;
                }
            }

            // Letezik-e mar?
            $existing = TeacherArchive::where('partner_id', $partner->id)
                ->where('canonical_name', $canonicalName)
                ->where(function ($q) use ($teacher) {
                    if ($teacher->school_id) {
                        $q->where('school_id', $teacher->school_id);
                    } else {
                        $q->whereNull('school_id');
                    }
                })
                ->first();

            if ($existing) {
                continue;
            }

            // Note-bol pozicio kinyerese (szoveges, pl. "igazgato", "osztalyfonok")
            $position = null;
            if ($teacher->note) {
                // "Eredeti nev: X | igazgato" -> "igazgato"
                $parts = explode('|', $teacher->note);
                $lastPart = trim(end($parts));
                if ($lastPart && ! str_starts_with($lastPart, 'Eredeti nev:')) {
                    $position = $lastPart;
                }
            }

            TeacherArchive::create([
                'partner_id' => $partner->id,
                'school_id' => $teacher->school_id,
                'canonical_name' => $canonicalName,
                'title_prefix' => $titlePrefix,
                'position' => $position,
                'is_active' => true,
            ]);

            $this->newTeacherArchive++;
        }

        $this->line("  Uj tanar archivum rekordok: {$this->newTeacherArchive}");
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('Osszesites:');
        $this->line("  Uj projektek: {$this->newProjects}");
        $this->line("  Frissitett: {$this->updatedProjects}");
        $this->line("  Valtozatlan: {$this->unchangedProjects}");
        $this->line("  Uj iskolak: {$this->newSchools}");
        $this->line("  Uj kontaktok: {$this->newContacts}");
        $this->line("  Uj szemelyek: {$this->newPersons}");
        $this->line("  Frissitett szemelyek: {$this->updatedPersons}");
        $this->line("  Uj tanar archivum: {$this->newTeacherArchive}");

        if ($this->errors > 0) {
            $this->error("  Hibak: {$this->errors}");
        }

        $this->newLine();
        $this->info('Szinkronizacio befejezve.');
    }
}
