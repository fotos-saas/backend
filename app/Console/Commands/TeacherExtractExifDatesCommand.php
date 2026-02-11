<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\Process;

class TeacherExtractExifDatesCommand extends Command
{
    protected $signature = 'teacher:extract-exif-dates
        {--dry-run : Csak kiírja mit csinálna}
        {--force : Felülírja a már kitöltött értékeket is}';

    protected $description = 'EXIF DateTimeOriginal kiolvasása a tanár fotókból és mentése custom_properties-be';

    private int $updated = 0;
    private int $skipped = 0;
    private int $noExif = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Media::where('model_type', 'App\\Models\\TeacherArchive')
            ->where('collection_name', 'teacher_photos');

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('custom_properties')
                    ->orWhere('custom_properties', '[]')
                    ->orWhere('custom_properties', '{}')
                    ->orWhereRaw("custom_properties::text NOT LIKE '%photo_taken_at%'");
            });
        }

        $total = $query->count();
        $this->info("Feldolgozandó media rekordok: {$total}");

        if ($total === 0) {
            $this->info('Nincs feldolgozandó rekord.');
            return 0;
        }

        DB::connection()->disableQueryLog();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($medias) use ($dryRun, $bar) {
            foreach ($medias as $media) {
                $bar->advance();
                $this->processMedia($media, $dryRun);
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info('Összesítés:');
        $this->line("  Frissítve: {$this->updated}");
        $this->line("  Kihagyva (már volt): {$this->skipped}");
        $this->line("  Nincs EXIF dátum: {$this->noExif}");
        if ($this->errors > 0) {
            $this->error("  Hibák: {$this->errors}");
        }

        return 0;
    }

    private function processMedia(Media $media, bool $dryRun): void
    {
        $filePath = $media->getPath();

        if (!file_exists($filePath)) {
            $this->errors++;
            return;
        }

        // Csak jpg/jpeg fájlokból próbálunk EXIF-et olvasni
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'tiff', 'tif'])) {
            $this->noExif++;
            return;
        }

        $process = new Process(['exiftool', '-DateTimeOriginal', '-s3', $filePath]);
        $process->setTimeout(5);

        try {
            $process->run();
        } catch (\Exception $e) {
            $this->errors++;
            return;
        }

        $output = trim($process->getOutput());

        if (empty($output) || $process->getExitCode() !== 0) {
            $this->noExif++;
            return;
        }

        // Format: "2022:12:09 12:11:56" → "2022-12-09"
        $date = $this->parseExifDate($output);
        if (!$date) {
            $this->noExif++;
            return;
        }

        if ($dryRun) {
            $this->updated++;
            return;
        }

        $media->setCustomProperty('photo_taken_at', $date);
        $media->save();
        $this->updated++;
    }

    private function parseExifDate(string $exifDate): ?string
    {
        // "2022:12:09 12:11:56" → "2022-12-09"
        $parts = explode(' ', $exifDate);
        if (empty($parts[0])) {
            return null;
        }

        $datePart = str_replace(':', '-', $parts[0]);

        // Validáció
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart)) {
            return null;
        }

        return $datePart;
    }
}
