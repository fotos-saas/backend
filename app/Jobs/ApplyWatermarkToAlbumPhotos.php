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

    public function __construct(
        public int $albumId
    ) {}

    public function handle(WatermarkService $watermarkService): void
    {
        $album = Album::findOrFail($this->albumId);

        $watermarkEnabled = Setting::get('watermark_enabled', true);
        if (! $watermarkEnabled) {
            Log::info('Watermark skipped - disabled in settings', [
                'album_id' => $this->albumId,
            ]);

            return;
        }

        // Resolve partner branding text
        $watermarkText = $this->resolveWatermarkText($album);

        $photos = $album->photos()->get();
        $processedCount = 0;

        foreach ($photos as $photo) {
            $media = $photo->getFirstMedia('photo');

            if (! $media) {
                continue;
            }

            if (! $media->hasGeneratedConversion('preview')) {
                continue;
            }

            $previewPath = $media->getPath('preview');
            if (! file_exists($previewPath)) {
                continue;
            }

            try {
                $watermarkService->applyTiledWatermark($previewPath, $watermarkText);
                $processedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to apply watermark in batch', [
                    'photo_id' => $photo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Batch watermarking completed', [
            'album_id' => $this->albumId,
            'album_title' => $album->title,
            'processed' => $processedCount,
            'total' => $photos->count(),
            'watermark_text' => $watermarkText,
        ]);
    }

    private function resolveWatermarkText(Album $album): string
    {
        $partner = $album->createdBy?->tabloPartner?->subscriptionPartner;

        if ($partner
            && $partner->hasFeature('branding')
            && $partner->branding
            && $partner->branding->is_active
            && ! empty($partner->branding->brand_name)
        ) {
            return $partner->branding->brand_name;
        }

        return Setting::get('watermark_text', 'Tablóstúdió');
    }
}
