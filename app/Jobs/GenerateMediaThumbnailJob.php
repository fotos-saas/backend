<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Jcupitt\Vips\Image as VipsImage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Aszinkron thumbnail generálás Spatie Media-hoz.
 *
 * A feltöltés után azonnal visszatér, a thumbnailek háttérben készülnek.
 * Ez jelentősen gyorsítja a bulk upload-ot.
 */
class GenerateMediaThumbnailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Thumbnail méretek
     */
    private const THUMB_SIZE = 300;
    private const PREVIEW_SIZE = 1200;

    /**
     * Job timeout (5 perc)
     */
    public int $timeout = 300;

    /**
     * Retry attempts
     */
    public int $tries = 3;

    /**
     * Backoff between retries
     */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $mediaId
    ) {}

    /**
     * Execute the job - thumbnail generálás libvips-szel.
     */
    public function handle(): void
    {
        $media = Media::find($this->mediaId);

        if (! $media) {
            Log::warning('GenerateMediaThumbnailJob: Media not found', ['media_id' => $this->mediaId]);

            return;
        }

        $originalPath = $media->getPath();

        if (! file_exists($originalPath)) {
            Log::warning('GenerateMediaThumbnailJob: Original file not found', [
                'media_id' => $this->mediaId,
                'path' => $originalPath,
            ]);

            return;
        }

        try {
            $conversionsDir = dirname($originalPath).'/conversions';
            if (! file_exists($conversionsDir)) {
                mkdir($conversionsDir, 0755, true);
            }

            $baseName = pathinfo($media->file_name, PATHINFO_FILENAME);
            $thumbPath = $conversionsDir.'/'.$baseName.'-thumb.jpg';

            // Thumbnail generálás libvips-szel (gyorsabb mint ImageMagick)
            $this->generateThumbnailWithVips($originalPath, $thumbPath, self::THUMB_SIZE);

            // Update Spatie media's generated_conversions JSON
            $generatedConversions = $media->generated_conversions ?? [];
            $generatedConversions['thumb'] = true;
            $media->generated_conversions = $generatedConversions;
            $media->save();

            Log::info('GenerateMediaThumbnailJob: Thumbnail generated', [
                'media_id' => $this->mediaId,
            ]);
        } catch (\Exception $e) {
            Log::error('GenerateMediaThumbnailJob: Failed to generate thumbnail', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for retry
        }
    }

    /**
     * Thumbnail generálás libvips-szel.
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

        // Sharpen slightly
        $image = $image->sharpen(['sigma' => 0.5, 'm1' => 1, 'm2' => 2]);

        // Save as JPEG with quality 85
        $image->jpegsave($outputPath, [
            'Q' => 85,
            'strip' => true,
            'optimize_coding' => true,
            'interlace' => true,
        ]);

        unset($image);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateMediaThumbnailJob: Job failed permanently', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);
    }
}
