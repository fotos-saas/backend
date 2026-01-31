<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\Setting;
use App\Services\WatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplyWatermarkToAlbumPhotos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $albumId
    ) {}

    /**
     * Execute the job - apply watermarks to all non-watermarked photos in album.
     */
    public function handle(WatermarkService $watermarkService): void
    {
        $album = Album::findOrFail($this->albumId);

        // Check global watermark settings
        $watermarkEnabled = Setting::get('watermark_enabled', true);
        $watermarkText = Setting::get('watermark_text', 'Tablokirály');

        if (! $watermarkEnabled || ! $watermarkText) {
            Log::info('Watermark skipped - disabled in settings', [
                'album_id' => $this->albumId,
            ]);

            return;
        }

        $photos = $album->photos()->get();
        $processedCount = 0;
        $skippedCount = 0;

        foreach ($photos as $photo) {
            $media = $photo->getFirstMedia('photo');

            if (! $media) {
                continue;
            }

            // Check if already watermarked (via custom property)
            $customProperties = $media->custom_properties;
            if (isset($customProperties['watermarked']) && $customProperties['watermarked'] === true) {
                $skippedCount++;
                continue;
            }

            // Check if preview conversion exists
            if (! $media->hasGeneratedConversion('preview')) {
                Log::warning('Preview conversion not found for watermarking', [
                    'photo_id' => $photo->id,
                    'media_id' => $media->id,
                ]);
                continue;
            }

            $previewPath = $media->getPath('preview');
            if (! file_exists($previewPath)) {
                Log::warning('Preview file does not exist for watermarking', [
                    'photo_id' => $photo->id,
                    'path' => $previewPath,
                ]);
                continue;
            }

            try {
                // Apply watermark
                $watermarkService->addCircularWatermark($previewPath, $watermarkText);

                // Mark as watermarked in custom properties
                $media->setCustomProperty('watermarked', true);
                $media->save();

                $processedCount++;

                Log::info('Watermark applied in batch processing', [
                    'photo_id' => $photo->id,
                    'media_id' => $media->id,
                    'preview_path' => $previewPath,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to apply watermark in batch processing', [
                    'photo_id' => $photo->id,
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Batch watermarking completed', [
            'album_id' => $this->albumId,
            'album_title' => $album->title,
            'processed' => $processedCount,
            'skipped' => $skippedCount,
            'total' => $photos->count(),
        ]);
    }
}
