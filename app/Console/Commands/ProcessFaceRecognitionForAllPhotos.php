<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFaceRecognition;
use App\Models\Photo;
use Illuminate\Console\Command;

/**
 * Artisan command to process face recognition for all photos.
 * Useful for batch processing existing photos.
 */
class ProcessFaceRecognitionForAllPhotos extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'photos:process-face-recognition
                            {--album= : Only process photos from specific album ID}
                            {--unprocessed : Only process photos that haven\'t been processed yet}
                            {--limit= : Limit the number of photos to process}';

    /**
     * The console command description.
     */
    protected $description = 'Process face recognition for photos and group them by faces';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting face recognition processing...');

        // Build query
        $query = Photo::query()->whereNotNull('path');

        // Filter by album if specified
        if ($albumId = $this->option('album')) {
            $query->where('album_id', $albumId);
            $this->info("Filtering photos from album ID: {$albumId}");
        }

        // Filter unprocessed only
        if ($this->option('unprocessed')) {
            $query->whereNull('face_detected');
            $this->info('Processing only unprocessed photos');
        }

        // Apply limit
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
            $this->info("Limiting to {$limit} photos");
        }

        $photos = $query->get();
        $totalPhotos = $photos->count();

        if ($totalPhotos === 0) {
            $this->warn('No photos found to process.');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalPhotos} photos to process");

        // Dispatch jobs
        $bar = $this->output->createProgressBar($totalPhotos);
        $bar->start();

        foreach ($photos as $photo) {
            ProcessFaceRecognition::dispatch($photo)->onQueue('face-recognition');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ Face recognition jobs queued successfully!');
        $this->info("Total photos queued: {$totalPhotos}");
        $this->info('Jobs are being processed by queue workers.');
        $this->newLine();
        $this->comment('Monitor progress with: php artisan queue:work --queue=face-recognition');

        return Command::SUCCESS;
    }
}
