<?php

namespace App\Listeners;

use App\Models\Setting;
use App\Services\WatermarkService;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class ApplyWatermarkToPreview
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected WatermarkService $watermarkService
    ) {}

    /**
     * Handle the event.
     *
     * This listener applies watermark to preview conversions automatically
     * after they have been completed (both sync and queued conversions).
     *
     * NOTE: Only applies to Photo model (album photos), NOT ConversionMedia (image converter).
     *
     * @param  ConversionHasBeenCompletedEvent  $event
     * @return void
     */
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        // Only react to 'preview' conversions
        if ($event->conversion->getName() !== 'preview') {
            return;
        }

        // Only apply watermark to Photo model (album photos)
        // Skip ConversionMedia (image converter) - no watermark needed there
        if ($event->media->model_type !== \App\Models\Photo::class) {
            Log::debug('Watermark skipped - not a Photo model', [
                'media_id' => $event->media->id,
                'model_type' => $event->media->model_type,
            ]);

            return;
        }

        // Check if already watermarked (via custom property)
        $customProperties = $event->media->custom_properties;
        if (isset($customProperties['watermarked']) && $customProperties['watermarked'] === true) {
            Log::debug('Watermark skipped - already watermarked', [
                'media_id' => $event->media->id,
            ]);

            return;
        }

        // Check global watermark settings
        $watermarkEnabled = Setting::get('watermark_enabled', true);
        $watermarkText = Setting::get('watermark_text', 'Tablokirály');

        if (! $watermarkEnabled || ! $watermarkText) {
            Log::info('Watermark skipped - disabled in settings', [
                'media_id' => $event->media->id,
                'conversion' => 'preview',
            ]);

            return;
        }

        // Get preview file path
        $previewPath = $event->media->getPath('preview');

        if (! file_exists($previewPath)) {
            Log::warning('Watermark skipped - preview file not found', [
                'media_id' => $event->media->id,
                'preview_path' => $previewPath,
            ]);

            return;
        }

        try {
            $this->watermarkService->addCircularWatermark($previewPath, $watermarkText);

            // Mark as watermarked in custom properties
            $event->media->setCustomProperty('watermarked', true);
            $event->media->save();

            Log::info('Watermark applied successfully via event listener', [
                'media_id' => $event->media->id,
                'preview_path' => $previewPath,
                'watermark_text' => $watermarkText,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the conversion process
            Log::error('Failed to apply watermark via event listener', [
                'media_id' => $event->media->id,
                'preview_path' => $previewPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
