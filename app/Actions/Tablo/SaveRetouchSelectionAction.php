<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;

class SaveRetouchSelectionAction
{
    /**
     * Auto-save retouch selection (without changing step)
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @param array $photoIds Media IDs from gallery
     * @return array Response data with cascade info
     */
    public function execute(User $user, TabloGallery $gallery, array $photoIds): array
    {
        // Find existing progress for this gallery
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already completed
        if ($progress && $progress->current_step === 'completed') {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
                'status' => 403,
            ];
        }

        // Check if workflow is finalized
        if ($progress && $progress->isFinalized()) {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
                'status' => 403,
            ];
        }

        // Find or create progress record
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'retouch',
                'steps_data' => [],
                'workflow_status' => TabloUserProgress::STATUS_IN_PROGRESS,
            ]
        );

        $stepsData = $progress->steps_data ?? [];

        // VALIDATION: Ensure all retouch photos are claimed photos
        $claimedMediaIds = $stepsData['claimed_media_ids'] ?? [];
        $requestedRetouchIds = $photoIds;

        // Filter: only keep retouch IDs that are in claimed set
        $validRetouchIds = array_values(array_intersect($requestedRetouchIds, $claimedMediaIds));

        // Log filtered photos
        if (count($validRetouchIds) < count($requestedRetouchIds)) {
            $filtered = array_diff($requestedRetouchIds, $validRetouchIds);
            \Log::warning('[SaveRetouchSelectionAction] Filtered non-claimed photos from retouch', [
                'user_id' => $user->id,
                'filtered_ids' => $filtered,
            ]);
        }

        // Track cascade deletions
        $cascadeDeleted = ['tablo' => false];

        // Cascade delete tablo_media_id if not in new retouch set
        if (isset($stepsData['tablo_media_id'])) {
            $tabloMediaId = $stepsData['tablo_media_id'];
            if (!in_array($tabloMediaId, $validRetouchIds)) {
                $cascadeDeleted['tablo'] = true;
                unset($stepsData['tablo_media_id']);
            }
        }

        $stepsData['retouch_media_ids'] = $validRetouchIds;
        $stepsData['retouch_count'] = count($validRetouchIds);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        // Build response
        $response = [
            'success' => true,
            'message' => 'Retusálási választás mentve',
            'retouch_photo_ids' => $validRetouchIds,
        ];

        if ($cascadeDeleted['tablo']) {
            $response['cascade_deleted'] = $cascadeDeleted;
            $response['cascade_message'] = 'A tablóképed frissítve lett';
        }

        return $response;
    }
}
