<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupOldZipsCommand extends Command
{
    /**
     * The name and signature of the console command
     */
    protected $signature = 'zips:cleanup
                          {--hours=24 : Delete ZIPs older than X hours}
                          {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description
     */
    protected $description = 'Régi ZIP fájlok törlése a storage-ból';

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $cutoffTime = Carbon::now()->subHours($hours);

        $this->info("🗑️  Régi ZIP fájlok keresése...");
        $this->info("Küszöb: {$cutoffTime->format('Y-m-d H:i:s')} ({$hours} órával ezelőtt)");

        if ($dryRun) {
            $this->warn('⚠️  DRY-RUN mód - semmit sem törlünk!');
        }

        $deletedCount = 0;
        $deletedSize = 0;
        $directories = Storage::directories('temp/zips');

        foreach ($directories as $directory) {
            // Get all ZIP files in this work session directory
            $files = Storage::files($directory);

            foreach ($files as $file) {
                if (!str_ends_with($file, '.zip')) {
                    continue;
                }

                $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

                if ($lastModified->lt($cutoffTime)) {
                    $size = Storage::size($file);

                    if ($dryRun) {
                        $this->line("🔍 Törlendő: {$file} (" . $this->formatBytes($size) . ", " . $lastModified->diffForHumans() . ")");
                    } else {
                        Storage::delete($file);
                        $this->line("✅ Törölve: {$file} (" . $this->formatBytes($size) . ")");
                    }

                    $deletedCount++;
                    $deletedSize += $size;
                }
            }

            // Clean up empty directories
            if (!$dryRun && empty(Storage::files($directory)) && empty(Storage::directories($directory))) {
                Storage::deleteDirectory($directory);
                $this->line("📁 Üres könyvtár törölve: {$directory}");
            }
        }

        $this->newLine();

        if ($deletedCount > 0) {
            if ($dryRun) {
                $this->warn("📊 {$deletedCount} fájl LENNE törölve ({$this->formatBytes($deletedSize)})");
            } else {
                $this->info("✅ {$deletedCount} fájl törölve ({$this->formatBytes($deletedSize)})");
            }
        } else {
            $this->info("✅ Nincs törlendő fájl");
        }

        return Command::SUCCESS;
    }

    /**
     * Format bytes to human-readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
