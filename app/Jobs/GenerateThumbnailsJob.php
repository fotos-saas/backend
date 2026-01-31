<?php

namespace App\Jobs;

use App\Models\ConversionJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jcupitt\Vips\Image as VipsImage;

class GenerateThumbnailsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum media to process per job chunk.
     * Prevents queue congestion with large batches.
     */
    public const CHUNK_SIZE = 30;

    /**
     * Thumbnail sizes (width x height)
     */
    private const THUMB_SIZE = 300;
    private const PREVIEW_SIZE = 1200;

    /**
     * The number of seconds the job can run before timing out.
     * Increased to 45 minutes for large batches.
     */
    public int $timeout = 2700; // 45 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 180, 300]; // 1min, 3min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ConversionJob $conversionJob
    ) {}

    /**
     * Execute the job: Generate thumbnails and previews using libvips
     *
     * This replaces Spatie's PerformConversionsJob with direct libvips processing
     * for 4-8x faster thumbnail generation and 10x less memory usage.
     */
    public function handle(): void
    {
        // Check if job is being deleted (skip processing)
        if (Cache::has("job_deleting_{$this->conversionJob->id}")) {
            Log::info("GenerateThumbnailsJob: Job {$this->conversionJob->id} is being deleted, skipping");
            return;
        }

        Log::info('Starting thumbnail generation (libvips) for job: '.$this->conversionJob->id);

        try {
            // Update job status to converting
            $this->conversionJob->update(['status' => 'converting']);

            // Count media waiting for thumbnail generation
            // (upload completed but thumbnail not yet generated)
            $pendingCount = $this->conversionJob->media()
                ->whereNotNull('upload_completed_at')
                ->whereNull('conversion_completed_at')
                ->count();

            // Get chunk of uploaded media that needs thumbnail generation
            $mediaRecords = $this->conversionJob->media()
                ->whereNotNull('upload_completed_at')
                ->whereNull('conversion_completed_at')
                ->with('media')
                ->limit(self::CHUNK_SIZE)
                ->get();

            if ($mediaRecords->isEmpty()) {
                Log::info('No media found for thumbnail generation');
                $this->conversionJob->update(['status' => 'completed']);

                return;
            }

            $totalMedia = $this->conversionJob->total_files;
            $processedCount = 0;

            Log::info("Processing thumbnail chunk of {$mediaRecords->count()} images (pending: {$pendingCount}, total: {$totalMedia})");

            foreach ($mediaRecords as $mediaRecord) {
                try {
                    // Mark thumbnail generation as started (keep conversion_status as 'pending')
                    // The conversion_status is reserved for ConvertImageBatchJob
                    $mediaRecord->update([
                        'conversion_started_at' => now(),
                    ]);

                    $spatieMedia = $mediaRecord->getFirstMedia('image_conversion');

                    if (! $spatieMedia) {
                        Log::warning('Spatie media not found for ConversionMedia: '.$mediaRecord->id);
                        $mediaRecord->update(['conversion_status' => 'failed']);
                        continue;
                    }

                    $originalPath = $spatieMedia->getPath();

                    if (!file_exists($originalPath)) {
                        Log::warning('Original file not found: '.$originalPath);
                        $mediaRecord->update(['conversion_status' => 'failed']);
                        continue;
                    }

                    // Generate output paths for conversions
                    $conversionsDir = dirname($originalPath).'/conversions';
                    if (!file_exists($conversionsDir)) {
                        mkdir($conversionsDir, 0755, true);
                    }

                    $baseName = pathinfo($spatieMedia->file_name, PATHINFO_FILENAME);
                    $thumbPath = $conversionsDir.'/'.$baseName.'-thumb.jpg';
                    $lightboxPath = $conversionsDir.'/'.$baseName.'-lightbox.jpg';

                    // Generate thumbnail (300x300) using libvips
                    $this->generateThumbnailWithVips($originalPath, $thumbPath, self::THUMB_SIZE);

                    // Generate lightbox (1200x1200) using libvips - NO WATERMARK
                    // Named 'lightbox' instead of 'preview' to avoid watermark listener
                    $this->generateThumbnailWithVips($originalPath, $lightboxPath, self::PREVIEW_SIZE);

                    // Update Spatie media's generated_conversions JSON
                    $generatedConversions = $spatieMedia->generated_conversions ?? [];
                    $generatedConversions['thumb'] = true;
                    $generatedConversions['lightbox'] = true;
                    $spatieMedia->generated_conversions = $generatedConversions;
                    $spatieMedia->save();

                    // Mark thumbnail generation as completed (keep conversion_status as 'pending')
                    // The conversion_status will be changed by ConvertImageBatchJob
                    $mediaRecord->update([
                        'conversion_completed_at' => now(),
                    ]);

                    Log::info('Thumbnail generation completed for media: '.$mediaRecord->id);
                } catch (\Exception $e) {
                    Log::error('Failed to generate thumbnails for media: '.$mediaRecord->id.', Error: '.$e->getMessage());
                    $mediaRecord->update(['conversion_status' => 'failed']);
                }

                // Update progress
                $processedCount++;
                $processedTotal = $this->conversionJob->processed_files + $processedCount;
                $progress = (int) round(($processedTotal / $totalMedia) * 100);
                Cache::put('conversion_job_'.$this->conversionJob->id.'_thumbnail_progress', $progress, now()->addHours(24));

                // Run garbage collection every 5 images to free memory
                if ($processedCount % 5 === 0) {
                    gc_collect_cycles();
                }
            }

            // Update job processed files count
            $this->conversionJob->increment('processed_files', $processedCount);

            // Check if there are more media waiting for thumbnail generation
            // (upload completed but thumbnail not yet generated)
            $remainingCount = $this->conversionJob->media()
                ->whereNotNull('upload_completed_at')
                ->whereNull('conversion_completed_at')
                ->count();

            if ($remainingCount > 0) {
                // Dispatch next chunk job
                Log::info("Dispatching next thumbnail chunk for job: {$this->conversionJob->id}, remaining: {$remainingCount}");
                self::dispatch($this->conversionJob)->delay(now()->addSeconds(3));
            } else {
                // All media processed - mark job as completed
                $this->conversionJob->update(['status' => 'completed']);
                Log::info('Thumbnail generation completed for job: '.$this->conversionJob->id);
            }

        } catch (\Exception $e) {
            Log::error('Thumbnail generation job failed: '.$this->conversionJob->id.', Error: '.$e->getMessage());
            $this->conversionJob->update(['status' => 'failed']);
        }
    }

    /**
     * Generate thumbnail using libvips (4-8x faster than ImageMagick)
     *
     * @param string $inputPath Source image path
     * @param string $outputPath Output thumbnail path
     * @param int $maxSize Maximum dimension (width or height)
     */
    private function generateThumbnailWithVips(string $inputPath, string $outputPath, int $maxSize): void
    {
        // Load image with sequential access for memory efficiency
        $image = VipsImage::newFromFile($inputPath, ['access' => 'sequential']);

        // Handle alpha channel (flatten to white background for JPEG)
        if ($image->hasAlpha()) {
            $image = $image->flatten(['background' => [255, 255, 255]]);
        }

        // Convert to sRGB colorspace for consistent colors
        $image = $image->colourspace('srgb');

        // Get current dimensions
        $width = $image->width;
        $height = $image->height;

        // Calculate scale factor to fit within maxSize (maintaining aspect ratio)
        if ($width > $maxSize || $height > $maxSize) {
            $scale = min($maxSize / $width, $maxSize / $height);
            $image = $image->resize($scale);
        }

        // Auto-rotate based on EXIF orientation
        $image = $image->autorot();

        // Sharpen slightly (equivalent to Spatie's sharpen(10))
        $image = $image->sharpen(['sigma' => 0.5, 'm1' => 1, 'm2' => 2]);

        // Save as JPEG with quality 85 (good balance for thumbnails)
        $image->jpegsave($outputPath, [
            'Q' => 85,
            'strip' => true,
            'optimize_coding' => true,
            'interlace' => true, // Progressive JPEG
        ]);

        // libvips automatically frees memory when object goes out of scope
        unset($image);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Thumbnail generation job failed: '.$this->conversionJob->id.', Exception: '.$exception->getMessage());
        $this->conversionJob->update(['status' => 'failed']);
    }
}
