<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGallery;

/**
 * Galéria képek törlése (egyedi és tömeges).
 */
class DeleteGalleryPhotosAction
{
    /**
     * Több kép törlése a galériából.
     *
     * @param TabloGallery $gallery
     * @param array<int> $photoIds
     * @return array{success: bool, message: string, deletedCount: int}
     */
    public function executeMany(TabloGallery $gallery, array $photoIds): array
    {
        $photos = $gallery->getMedia('photos')->whereIn('id', $photoIds);

        $deletedCount = 0;
        foreach ($photos as $media) {
            $media->delete();
            $deletedCount++;
        }

        return [
            'success' => true,
            'message' => "{$deletedCount} kép sikeresen törölve",
            'deletedCount' => $deletedCount,
        ];
    }

    /**
     * Egyetlen kép törlése a galériából.
     *
     * @param TabloGallery $gallery
     * @param int $mediaId
     * @return array{success: bool, message: string, status?: int}
     */
    public function executeOne(TabloGallery $gallery, int $mediaId): array
    {
        $media = $gallery->getMedia('photos')->firstWhere('id', $mediaId);

        if (!$media) {
            return [
                'success' => false,
                'message' => 'A kép nem található ebben a galériában.',
                'status' => 404,
            ];
        }

        $media->delete();

        return [
            'success' => true,
            'message' => 'Kép sikeresen törölve',
        ];
    }
}
