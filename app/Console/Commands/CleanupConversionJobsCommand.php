<?php

namespace App\Console\Commands;

use App\Jobs\DeleteConversionJobAsync;
use App\Models\ConversionJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Automatikus cleanup parancs a régi konverziós munkákhoz.
 *
 * Törli azokat a ConversionJob-okat (és a hozzájuk tartozó media fájlokat),
 * amelyek X óránál régebbiek. Alapértelmezetten 48 óra.
 *
 * Használat:
 *   php artisan cleanup:conversion-jobs --dry-run     # Csak listázza
 *   php artisan cleanup:conversion-jobs --hours=24   # 24 óránál régebbiek
 *   php artisan cleanup:conversion-jobs --force      # Kérdés nélkül töröl
 */
class CleanupConversionJobsCommand extends Command
{
    protected $signature = 'cleanup:conversion-jobs
                            {--hours=48 : Órák száma, ami után törlődik a job}
                            {--dry-run : Csak listázza, mit törölne}
                            {--force : Kérdés nélkül töröl (scheduler-hez)}';

    protected $description = 'Törli a régi konverziós munkákat és a hozzájuk tartozó média fájlokat';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $cutoffDate = Carbon::now()->subHours($hours);

        $this->info("Keresés: {$hours} óránál régebbi konverziós munkák...");
        $this->info("Határidő: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->newLine();

        $jobs = ConversionJob::where('created_at', '<', $cutoffDate)
            ->withCount('media')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($jobs->isEmpty()) {
            $this->info("Nincs {$hours} óránál régebbi konverziós munka.");

            return Command::SUCCESS;
        }

        $totalFiles = $jobs->sum('media_count');

        $this->info("Találtam {$jobs->count()} régi munkát ({$totalFiles} fájllal):");
        $this->newLine();

        $this->table(
            ['ID', 'Név', 'Létrehozva', 'Státusz', 'Fájlok'],
            $jobs->map(fn ($j) => [
                $j->id,
                mb_substr($j->job_name ?? '-', 0, 30),
                $j->created_at->format('Y-m-d H:i'),
                $j->status,
                $j->media_count,
            ])
        );

        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY-RUN mód - semmi sem lett törölve.');
            $this->info("Futtasd --force vagy erősítsd meg a törlést a tényleges művelethez.");

            return Command::SUCCESS;
        }

        if (! $force && ! $this->confirm("Törlöd ezt a {$jobs->count()} munkát ({$totalFiles} fájl)?")) {
            $this->info('Művelet megszakítva.');

            return Command::SUCCESS;
        }

        $deleted = 0;
        $errors = 0;

        $this->info('Törlés indítása...');

        foreach ($jobs as $job) {
            try {
                // Async job dispatch a törléshez (ugyanaz mint a manuális törlés)
                DeleteConversionJobAsync::dispatch($job->id);
                $deleted++;
                $this->line("  ✓ Job #{$job->id} ({$job->media_count} fájl) törlése ütemezve");
            } catch (\Exception $e) {
                $errors++;
                $this->error("  ✗ Job #{$job->id} hiba: {$e->getMessage()}");
            }
        }

        $this->newLine();

        if ($errors > 0) {
            $this->warn("Összesen {$deleted} munka törlése ütemezve, {$errors} hibával.");

            return Command::FAILURE;
        }

        $this->info("Összesen {$deleted} munka törlése ütemezve.");
        $this->info('A tényleges törlés a queue worker-en keresztül történik.');

        return Command::SUCCESS;
    }
}
