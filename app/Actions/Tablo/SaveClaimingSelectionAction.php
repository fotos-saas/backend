<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;

class SaveClaimingSelectionAction
{
    /**
     * Save claiming selection (photos user claimed as their own)
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

        // Find or create progress record
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'claiming',
                'steps_data' => [],
            ]
        );

        $stepsData = $progress->steps_data ?? [];
        $claimedSet = $photoIds;

        // Track cascade deletions for frontend notification
        $cascadeDeleted = [
            'retouch' => [],
            'tablo' => false,
        ];

        // IMPORTANT: Cascade delete - clean retouch_media_ids
        // Invariant: retouch_media_ids ⊆ claimed_media_ids
        if (isset($stepsData['retouch_media_ids']) && is_array($stepsData['retouch_media_ids'])) {
            $retouchIds = $stepsData['retouch_media_ids'];
            $cleanedRetouchIds = array_values(array_intersect($retouchIds, $claimedSet));

            $removedFromRetouch = array_values(array_diff($retouchIds, $cleanedRetouchIds));
            if (!empty($removedFromRetouch)) {
                $cascadeDeleted['retouch'] = $removedFromRetouch;
            }

            $stepsData['retouch_media_ids'] = $cleanedRetouchIds;
            $stepsData['retouch_count'] = count($cleanedRetouchIds);
        }

        // IMPORTANT: Cascade delete - clean tablo_media_id
        if (isset($stepsData['tablo_media_id'])) {
            $tabloMediaId = $stepsData['tablo_media_id'];
            $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];
            $validTabloSet = !empty($retouchMediaIds) ? $retouchMediaIds : $claimedSet;

            if (!in_array($tabloMediaId, $validTabloSet)) {
                $cascadeDeleted['tablo'] = true;
                unset($stepsData['tablo_media_id']);
            }
        }

        $stepsData['claimed_media_ids'] = $claimedSet;
        $stepsData['claimed_count'] = count($claimedSet);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        // Build response
        $response = [
            'success' => true,
            'message' => 'Képválasztás mentve',
        ];

        $hasCascade = !empty($cascadeDeleted['retouch']) || $cascadeDeleted['tablo'];
        if ($hasCascade) {
            $response['cascade_deleted'] = $cascadeDeleted;
            $response['cascade_message'] = $this->buildCascadeMessage($cascadeDeleted);
        }

        return $response;
    }

    /**
     * Build cascade deletion message for frontend toast
     */
    private function buildCascadeMessage(array $cascadeDeleted): string
    {
        $parts = [];

        if (!empty($cascadeDeleted['retouch'])) {
            $count = count($cascadeDeleted['retouch']);
            $parts[] = "{$count} kép eltávolítva a retusálás listádból";
        }

        if ($cascadeDeleted['tablo']) {
            $parts[] = 'A tablóképed frissítve lett';
        }

        return implode('. ', $parts);
    }
}
