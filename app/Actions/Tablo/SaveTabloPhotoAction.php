<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;

class SaveTabloPhotoAction
{
    /**
     * Save tablo photo selection
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @param int $photoId Media ID from gallery
     * @return array Response data
     */
    public function execute(User $user, TabloGallery $gallery, int $photoId): array
    {
        // Find existing progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is finalized
        if ($progress && $progress->isFinalized()) {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
                'status' => 403,
            ];
        }

        if (!$progress) {
            return [
                'success' => false,
                'error' => 'Nincs progress adat ehhez a galériához',
                'status' => 404,
            ];
        }

        // Get steps data for validation
        $stepsData = $progress->steps_data ?? [];
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];

        // VALIDATION: Ensure tablo photo is in retouch photos
        if (!empty($retouchMediaIds) && !in_array($photoId, $retouchMediaIds)) {
            return [
                'success' => false,
                'error' => 'A kiválasztott kép nincs a retusálandó képek között',
                'status' => 400,
            ];
        }

        // Save tablo media ID
        $stepsData['tablo_media_id'] = $photoId;

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        return [
            'success' => true,
            'message' => 'Tablókép választás mentve',
            'tablo_photo_id' => $photoId,
        ];
    }

    /**
     * Clear tablo photo selection
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @return array Response data
     */
    public function clear(User $user, TabloGallery $gallery): array
    {
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if (!$progress) {
            return [
                'success' => false,
                'error' => 'Nincs mentett haladás',
                'status' => 404,
            ];
        }

        if ($progress->current_step === 'completed') {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
                'status' => 403,
            ];
        }

        // Remove tablo_media_id from steps_data
        $stepsData = $progress->steps_data ?? [];
        unset($stepsData['tablo_media_id']);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        return [
            'success' => true,
            'message' => 'Tablókép kijelölés törölve',
        ];
    }
}
