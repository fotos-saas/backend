<?php

namespace App\Listeners;

use App\Models\PartnerAlbum;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\TabloProject;
use App\Services\WatermarkService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class ApplyWatermarkToPreview
{
    public function __construct(
        protected WatermarkService $watermarkService
    ) {}

    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        if ($event->conversion->getName() !== 'preview') {
            return;
        }

        // Only watermark these model types
        $allowedModels = [
            Photo::class,
            PartnerAlbum::class,
            TabloProject::class,
        ];

        if (! in_array($event->media->model_type, $allowedModels)) {
            Log::debug('Watermark skipped - unsupported model type', [
                'media_id' => $event->media->id,
                'model_type' => $event->media->model_type,
            ]);

            return;
        }

        $watermarkEnabled = Setting::get('watermark_enabled', true);
        if (! $watermarkEnabled) {
            return;
        }

        $previewPath = $event->media->getPath('preview');

        if (! file_exists($previewPath)) {
            Log::warning('Watermark skipped - preview file not found', [
                'media_id' => $event->media->id,
            ]);

            return;
        }

        try {
            $model = $event->media->model;
            $text = $model ? $this->resolveWatermarkText($model) : $this->fallbackText();

            $this->watermarkService->applyTiledWatermark($previewPath, $text);

            Log::info('Tiled watermark applied via listener', [
                'media_id' => $event->media->id,
                'model_type' => $event->media->model_type,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to apply watermark via listener', [
                'media_id' => $event->media->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve watermark text based on partner branding.
     *
     * Chain: Model → TabloPartner → subscriptionPartner (Partner) → branding (PartnerBranding)
     */
    private function resolveWatermarkText(Model $model): string
    {
        $partner = null;

        if ($model instanceof Photo) {
            // Photo → Album → createdBy (User) → tabloPartner → subscriptionPartner
            $partner = $model->album?->createdBy?->tabloPartner?->subscriptionPartner;
        } elseif ($model instanceof PartnerAlbum) {
            // PartnerAlbum → partner (TabloPartner) → subscriptionPartner
            $partner = $model->partner?->subscriptionPartner;
        } elseif ($model instanceof TabloProject) {
            // TabloProject → partner (TabloPartner) → subscriptionPartner
            $partner = $model->partner?->subscriptionPartner;
        }

        if ($partner
            && $partner->hasFeature('branding')
            && $partner->branding
            && $partner->branding->is_active
            && ! empty($partner->branding->brand_name)
        ) {
            return $partner->branding->brand_name;
        }

        return $this->fallbackText();
    }

    private function fallbackText(): string
    {
        return Setting::get('watermark_text', 'Tablóstúdió');
    }
}
