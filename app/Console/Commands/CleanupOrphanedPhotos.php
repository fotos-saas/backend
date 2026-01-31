<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:cleanup-orphaned {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Törli azokat a képbejegyzéseket az adatbázisból, amelyekhez nem létezik a fájl';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Képbejegyzések ellenőrzése...');

        $photos = Photo::all();
        $orphanedPhotos = [];
        $totalPhotos = $photos->count();

        $progressBar = $this->output->createProgressBar($totalPhotos);
        $progressBar->start();

        foreach ($photos as $photo) {
            // Check if file exists in storage/app/public/
            $filePath = storage_path('app/public/'.$photo->path);

            if (! file_exists($filePath)) {
                $orphanedPhotos[] = $photo;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if (empty($orphanedPhotos)) {
            $this->info('✅ Nem találtam árva képbejegyzéseket.');

            return 0;
        }

        $this->warn('🔍 Találtam '.count($orphanedPhotos).' árva képbejegyzést:');
        $this->newLine();

        // Táblázat a találatokról
        $tableData = [];
        foreach ($orphanedPhotos as $photo) {
            $userName = $photo->assignedUser ? $photo->assignedUser->name : 'Nincs hozzárendelve';
            $tableData[] = [
                'ID' => $photo->id,
                'Útvonal' => $photo->path,
                'Album' => $photo->album->title ?? 'N/A',
                'Felhasználó' => $userName,
            ];
        }

        $this->table(['ID', 'Útvonal', 'Album', 'Felhasználó'], $tableData);

        if ($isDryRun) {
            $this->info('🔍 Dry-run mód: Nem törlök semmit. Futtasd --dry-run nélkül a törléshez.');

            return 0;
        }

        if (! $this->confirm('Biztosan törölni szeretnéd ezeket a bejegyzéseket?')) {
            $this->info('Megszakítva.');

            return 0;
        }

        $deletedCount = 0;
        foreach ($orphanedPhotos as $photo) {
            $this->line("Törlöm: {$photo->path} (ID: {$photo->id})");
            $photo->delete();
            $deletedCount++;
        }

        $this->info("✅ Sikeresen töröltem {$deletedCount} árva képbejegyzést.");

        return 0;
    }
}
