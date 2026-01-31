<?php

namespace App\Jobs;

use App\Models\ConversionJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jcupitt\Vips\Image as VipsImage;

class ConvertImageBatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum images to process per job chunk.
     * Prevents memory exhaustion with large batches.
     * With libvips, can handle more images per chunk due to lower memory usage.
     */
    public const CHUNK_SIZE = 30;

    /**
     * The number of seconds the job can run before timing out.
     * Increased to 30 minutes for larger batches.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [30, 60, 120]; // 30s, 1min, 2min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ConversionJob $conversionJob
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if job is being deleted (skip processing)
        if (Cache::has("job_deleting_{$this->conversionJob->id}")) {
            Log::info("ConvertImageBatchJob: Job {$this->conversionJob->id} is being deleted, skipping");
            return;
        }

        Log::info('Starting image conversion for job: '.$this->conversionJob->id);

        try {
            // Get pending media count for this job
            $pendingCount = $this->conversionJob->media()
                ->where('conversion_status', 'pending')
                ->count();

            // Get chunk of media for this job with eager loaded Spatie media
            $mediaRecords = $this->conversionJob->media()
                ->where('conversion_status', 'pending')
                ->with('media')
                ->limit(self::CHUNK_SIZE)
                ->get();

            $totalMedia = $this->conversionJob->total_files;
            $processedCount = 0;

            Log::info("Processing chunk of {$mediaRecords->count()} images (pending: {$pendingCount}, total: {$totalMedia})");

            foreach ($mediaRecords as $mediaRecord) {
                try {
                    // Update media status to converting
                    $mediaRecord->update(['conversion_status' => 'converting']);

                    $media = $mediaRecord->getFirstMedia('image_conversion');

                    if (! $media) {
                        $mediaRecord->update(['conversion_status' => 'failed']);
                        continue;
                    }

                    // Get original file path
                    $originalPath = $media->getPath();

                    // Ensure temp directory exists
                    $tempDir = storage_path('app/temp');
                    if (! file_exists($tempDir)) {
                        mkdir($tempDir, 0755, true);
                    }

                    $convertedPath = $tempDir.'/converted_'.$media->id.'.jpg';

                    // Get config values
                    $maxSize = config('image.conversion.max_dimension', 3000);
                    $quality = config('image.conversion.quality', 90);
                    $driver = config('image.driver', 'vips');

                    if ($driver === 'vips') {
                        // Use libvips for high-performance image processing
                        // 4-8x faster, 10x less memory than ImageMagick
                        $this->convertWithVips($originalPath, $convertedPath, $maxSize, $quality);
                    } else {
                        // Fallback to ImageMagick
                        $this->convertWithImagick($originalPath, $convertedPath, $maxSize, $quality);
                    }

                    // Replace original with converted
                    $media->delete();

                    // Re-add as converted
                    $originalName = $media->getCustomProperty('original_name');
                    $folderPath = $media->getCustomProperty('folder_path');

                    $newMedia = $mediaRecord->addMedia($convertedPath)
                        ->usingFileName(basename($convertedPath))
                        ->withCustomProperties([
                            'original_name' => $originalName,
                            'folder_path' => $folderPath,
                        ])
                        ->toMediaCollection('image_conversion', 'public');

                    // Clean up temp file
                    if (file_exists($convertedPath)) {
                        unlink($convertedPath);
                    }

                    // Update media status to completed
                    $mediaRecord->update(['conversion_status' => 'completed']);

                    Log::info('Converted image: '.$media->id);
                } catch (\Exception $e) {
                    Log::error('Failed to convert image: '.$mediaRecord->id.', Error: '.$e->getMessage());
                    $mediaRecord->update(['conversion_status' => 'failed']);
                }

                // Update progress
                $processedCount++;
                $processedTotal = $this->conversionJob->processed_files + $processedCount;
                $progress = (int) round(($processedTotal / $totalMedia) * 100);
                Cache::put('conversion_job_'.$this->conversionJob->id.'_progress', $progress, now()->addHours(24));

                // Run garbage collection every 5 images to free memory
                if ($processedCount % 5 === 0) {
                    gc_collect_cycles();
                }
            }

            // Update job processed files count
            $this->conversionJob->increment('processed_files', $processedCount);

            // Check if there are more pending images to process
            $remainingCount = $this->conversionJob->media()
                ->where('conversion_status', 'pending')
                ->count();

            if ($remainingCount > 0) {
                // Dispatch next chunk job
                Log::info("Dispatching next chunk for job: {$this->conversionJob->id}, remaining: {$remainingCount}");
                self::dispatch($this->conversionJob)->delay(now()->addSeconds(2));
            } else {
                // Update job status to completed
                $this->conversionJob->update(['status' => 'completed']);
                Log::info('Completed image conversion for job: '.$this->conversionJob->id);
            }

        } catch (\Exception $e) {
            Log::error('Job conversion failed: '.$this->conversionJob->id.', Error: '.$e->getMessage());
            $this->conversionJob->update(['status' => 'failed']);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed: '.$this->conversionJob->id.', Exception: '.$exception->getMessage());
        $this->conversionJob->update(['status' => 'failed']);
    }

    /**
     * Convert image using libvips (high-performance).
     * 4-8x faster and uses 10x less memory than ImageMagick.
     */
    private function convertWithVips(string $inputPath, string $outputPath, int $maxSize, int $quality): void
    {
        // Load image with sequential access for memory efficiency
        $image = VipsImage::newFromFile($inputPath, ['access' => 'sequential']);

        // Convert color space to sRGB if not already
        // This handles Display P3, Adobe RGB, ProPhoto RGB → sRGB
        if ($image->hasAlpha()) {
            // Flatten alpha channel for JPEG output
            $image = $image->flatten(['background' => [255, 255, 255]]);
        }

        // Convert to sRGB colorspace
        $image = $image->colourspace('srgb');

        // Resize if needed (max dimension on any side)
        $width = $image->width;
        $height = $image->height;

        if ($width > $maxSize || $height > $maxSize) {
            // Calculate scale factor to fit within maxSize
            $scale = min($maxSize / $width, $maxSize / $height);
            $image = $image->resize($scale);
        }

        // Auto-rotate based on EXIF orientation, then strip metadata
        $image = $image->autorot();

        // Save as JPEG with specified quality
        // strip: true removes all metadata (EXIF, GPS, ICC profile, etc.)
        $image->jpegsave($outputPath, [
            'Q' => $quality,
            'strip' => true,
            'optimize_coding' => true,
            'interlace' => true, // Progressive JPEG
        ]);

        // libvips automatically frees memory when object goes out of scope
        unset($image);
    }

    /**
     * Convert image using ImageMagick (fallback).
     * Used when libvips is not available or fails.
     */
    private function convertWithImagick(string $inputPath, string $outputPath, int $maxSize, int $quality): void
    {
        $imagick = new \Imagick($inputPath);

        // Convert color space to sRGB
        $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);

        // Strip ALL metadata (EXIF, GPS, camera info, ICC profile, etc.)
        $imagick->stripImage();

        // Resize if needed
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width > $maxSize || $height > $maxSize) {
            $imagick->thumbnailImage($maxSize, $maxSize, true);
        }

        // Set JPEG format and quality
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality($quality);

        // Save converted image
        $imagick->writeImage($outputPath);

        // Free memory
        $imagick->clear();
        $imagick->destroy();
        unset($imagick);
    }
}
