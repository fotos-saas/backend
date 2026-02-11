<?php

namespace App\Console\Commands;

use App\Models\TabloPartner;
use App\Models\TabloProject;
use App\Models\TeacherArchive;
use App\Models\TeacherPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TabloImportImagesCommand extends Command
{
    private const NEW_PARTNER_ID = 24;

    protected $signature = 'tablo:import-images
        {--type= : Csak adott tipust importal (samples|teachers|all)}
        {--dry-run : Csak kiirja mit csinalna}
        {--force : Megerosites kihagyasa}';

    protected $description = 'Importalja a letoltott kepeket Spatie Media Library-be';

    private int $importedSamples = 0;
    private int $skippedSamples = 0;
    private int $importedTeachers = 0;
    private int $skippedTeachers = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $type = $this->option('type') ?? 'all';
        $dryRun = $this->option('dry-run');

        $basePath = storage_path('app/migration_images');
        if (! is_dir($basePath)) {
            $this->error("Migration images mappa nem talalhato: {$basePath}");
            return 1;
        }

        $dirs = collect(File::directories($basePath))->sort();
        $this->info("Talalt projekt mappak: {$dirs->count()}");

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Importalas indul. Folytatod?')) {
                return 0;
            }
        }

        DB::connection()->disableQueryLog();

        if (in_array($type, ['all', 'samples'])) {
            $this->importSamples($dirs, $basePath, $dryRun);
        }

        if (in_array($type, ['all', 'teachers'])) {
            $this->importTeacherPhotos($basePath, $dryRun);
        }

        $this->newLine();
        $this->info('Osszesites:');
        $this->line("  Importalt mintak: {$this->importedSamples} (skip: {$this->skippedSamples})");
        $this->line("  Importalt tanar kepek: {$this->importedTeachers} (skip: {$this->skippedTeachers})");
        if ($this->errors > 0) {
            $this->error("  Hibak: {$this->errors}");
        }
        $this->info('Kesz.');

        return 0;
    }

    private function importSamples($dirs, string $basePath, bool $dryRun): void
    {
        $this->newLine();
        $this->info('1/2 Tablo mintak importalasa (samples)...');

        $bar = $this->output->createProgressBar($dirs->count());
        $bar->start();

        foreach ($dirs as $dir) {
            $externalId = basename($dir);
            $bar->advance();

            // Sample kep keresese
            $sampleFiles = glob("{$dir}/sample.*");
            if (empty($sampleFiles)) {
                continue;
            }

            $sampleFile = $sampleFiles[0];

            // Projekt keresese
            $project = TabloProject::where('external_id', $externalId)->first();
            if (! $project) {
                continue;
            }

            // Mar van sample media?
            if ($project->getFirstMedia('samples')) {
                $this->skippedSamples++;
                continue;
            }

            if ($dryRun) {
                $this->importedSamples++;
                continue;
            }

            try {
                $project->addMedia($sampleFile)
                    ->preservingOriginal()
                    ->toMediaCollection('samples', 'public');
                $this->importedSamples++;
            } catch (\Exception $e) {
                $this->errors++;
                $this->newLine();
                $this->warn("  Hiba sample #{$externalId}: {$e->getMessage()}");
            }

            // Memoria felszabaditas 100 projektenkent
            if (($this->importedSamples + $this->skippedSamples) % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->line("  Mintak: {$this->importedSamples} importalva, {$this->skippedSamples} skip");
    }

    private function importTeacherPhotos(string $basePath, bool $dryRun): void
    {
        $this->newLine();
        $this->info('2/2 Tanar kepek importalasa (teacher_archive)...');

        $partner = TabloPartner::find(self::NEW_PARTNER_ID);
        if (! $partner) {
            $this->error('Partner nem talalhato!');
            return;
        }

        // Osszes teacher archive rekord a partnerhez
        $teachers = TeacherArchive::where('partner_id', $partner->id)
            ->with('media')
            ->get();

        $this->line("  Teacher archive rekordok: {$teachers->count()}");

        // Osszes projekt a partnerhez, external_id => project
        $projects = TabloProject::where('partner_id', $partner->id)
            ->whereNotNull('external_id')
            ->get()
            ->keyBy('external_id');

        // tablo_persons lekerdezese: teacher nev + project_id + local_id
        $personMap = DB::table('tablo_persons as tp')
            ->join('tablo_projects as tpr', 'tpr.id', '=', 'tp.tablo_project_id')
            ->where('tp.type', 'teacher')
            ->where('tpr.partner_id', $partner->id)
            ->select('tp.name', 'tp.local_id', 'tpr.external_id', 'tpr.school_id')
            ->get();

        // Teacher archive-hez keresunk kepet: canonical_name + school_id -> fajl utak
        $bar = $this->output->createProgressBar($teachers->count());
        $bar->start();

        foreach ($teachers as $teacher) {
            $bar->advance();

            // Mar van media?
            if ($teacher->getFirstMedia('teacher_photos')) {
                $this->skippedTeachers++;
                continue;
            }

            // Keressuk a tablo_persons rekordot ami ehhez a teacher archive-hoz tartozik
            $matchingPersons = $personMap->filter(function ($p) use ($teacher) {
                $name = trim($p->name);
                // Prefix eltavolitasa az osszehasonlitashoz
                $prefixes = ['Dr.', 'Prof.', 'dr.', 'prof.', 'Id.', 'Ifj.', 'id.', 'ifj.', 'özv.', 'Özv.'];
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($name, $prefix . ' ')) {
                        $name = trim(substr($name, strlen($prefix)));
                        break;
                    }
                }

                $nameMatch = $name === $teacher->canonical_name;
                $schoolMatch = $teacher->school_id
                    ? $p->school_id == $teacher->school_id
                    : $p->school_id === null;

                return $nameMatch && $schoolMatch;
            });

            if ($matchingPersons->isEmpty()) {
                continue;
            }

            // Keressuk a kepet a fajlrendszerben
            $imageFile = null;
            foreach ($matchingPersons as $person) {
                $projectDir = "{$basePath}/{$person->external_id}/teachers";
                if (! is_dir($projectDir)) {
                    continue;
                }

                // Keresunk local_id-vel vegzodo fajlt
                $files = glob("{$projectDir}/*_{$person->local_id}.*");
                if (! empty($files)) {
                    $imageFile = $files[0];
                    break;
                }
            }

            if (! $imageFile || ! file_exists($imageFile)) {
                continue;
            }

            if ($dryRun) {
                $this->importedTeachers++;
                continue;
            }

            try {
                $media = $teacher->addMedia($imageFile)
                    ->preservingOriginal()
                    ->toMediaCollection('teacher_photos');

                // TeacherPhoto pivot rekord letrehozasa
                $hasActive = TeacherPhoto::where('teacher_id', $teacher->id)
                    ->where('is_active', true)
                    ->exists();

                TeacherPhoto::create([
                    'teacher_id' => $teacher->id,
                    'media_id' => $media->id,
                    'year' => (int) date('Y'),
                    'is_active' => ! $hasActive,
                ]);

                // active_photo_id beallitasa ha meg nincs
                if (! $hasActive && ! $teacher->active_photo_id) {
                    $teacher->update(['active_photo_id' => $media->id]);
                }

                $this->importedTeachers++;
            } catch (\Exception $e) {
                $this->errors++;
                $this->newLine();
                $this->warn("  Hiba teacher '{$teacher->canonical_name}': {$e->getMessage()}");
            }

            if ($this->importedTeachers % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->line("  Tanarok: {$this->importedTeachers} importalva, {$this->skippedTeachers} skip");
    }
}
