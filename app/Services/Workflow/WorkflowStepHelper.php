<?php

namespace App\Services\Workflow;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared helper methods for tablo workflow steps.
 *
 * Központosítja a step metadata és media formázás logikát,
 * amit korábban a TabloWorkflowService-ben duplikáltunk.
 */
class WorkflowStepHelper
{
    /**
     * Get step metadata (rules, limits) - unified for both session and gallery workflows.
     *
     * Korábban getStepMetadata() és getGalleryStepMetadata() külön metódusok voltak,
     * de a logikájuk azonos volt, csak a max_retouch_photos forrása különbözött.
     *
     * @param  string  $step  Current step
     * @param  int|null  $maxRetouchPhotos  Max retouch photo limit (from session or gallery/project)
     * @return array{allow_multiple: bool, max_selection: int|null, description: string}
     */
    public static function getStepMetadata(string $step, ?int $maxRetouchPhotos = null): array
    {
        $metadata = [
            'allow_multiple' => true,
            'max_selection' => null,
            'description' => '',
        ];

        if ($step === 'claiming') {
            $metadata['description'] = 'Válaszd ki saját képeidet';
        }

        if ($step === 'registration') {
            $metadata['description'] = 'Regisztráció név és email megadásával';
        }

        if ($step === 'retouch') {
            $metadata['max_selection'] = $maxRetouchPhotos;
            $metadata['description'] = 'Válaszd ki a retusálandó képeket';
        }

        if ($step === 'tablo') {
            $metadata['allow_multiple'] = false;
            $metadata['max_selection'] = 1;
            $metadata['description'] = 'Válassz egy képet tablóra';
        }

        if ($step === 'completed') {
            $metadata['description'] = 'Rendelés véglegesítve';
        }

        return $metadata;
    }

    /**
     * Format a Spatie Media item for frontend response.
     *
     * Korábban inline closure volt 2x a TabloWorkflowService-ben
     * (getVisiblePhotosFromGallery és buildReviewGroups metódusokban).
     */
    public static function formatMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumbnail_url' => $media->getUrl('thumb'),
            'preview_url' => $media->getUrl('preview'),
            'filename' => $media->file_name,
            'size' => $media->human_readable_size,
            'created_at' => $media->created_at->toIso8601String(),
        ];
    }

    /**
     * Format media item for review groups (simplified, no size/created_at).
     */
    public static function formatMediaForReview(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'thumbnail_url' => $media->getUrl('thumb'),
            'preview_url' => $media->getUrl('preview'),
            'filename' => $media->file_name,
        ];
    }
}
