<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;

class FinalizeWorkflowAction
{
    /**
     * Finalize workflow - mark as completed (no more modifications allowed)
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @return array Response data
     */
    public function execute(User $user, TabloGallery $gallery): array
    {
        // Find existing progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already finalized
        if ($progress && $progress->isFinalized()) {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett',
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

        // Validate workflow is ready to finalize
        $stepsData = $progress->steps_data ?? [];
        $claimedMediaIds = $stepsData['claimed_media_ids'] ?? [];
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];
        $tabloMediaId = $stepsData['tablo_media_id'] ?? null;

        // Validation: need claimed photos
        if (empty($claimedMediaIds)) {
            return [
                'success' => false,
                'error' => 'Nincs kiválasztott kép',
                'status' => 400,
            ];
        }

        // Validation: need tablo photo
        if (!$tabloMediaId) {
            return [
                'success' => false,
                'error' => 'Nincs tablókép kiválasztva',
                'status' => 400,
            ];
        }

        // Validation: tablo photo must be in retouch photos (if retouch step used)
        if (!empty($retouchMediaIds) && !in_array($tabloMediaId, $retouchMediaIds)) {
            return [
                'success' => false,
                'error' => 'A tablókép nincs a retusálandó képek között',
                'status' => 400,
            ];
        }

        // Finalize workflow
        $progress->update([
            'current_step' => 'completed',
            'workflow_status' => TabloUserProgress::STATUS_FINALIZED,
            'finalized_at' => now(),
        ]);

        \Log::info('[FinalizeWorkflowAction] Workflow finalized', [
            'user_id' => $user->id,
            'gallery_id' => $gallery->id,
            'claimed_count' => count($claimedMediaIds),
            'retouch_count' => count($retouchMediaIds),
            'tablo_media_id' => $tabloMediaId,
        ]);

        return [
            'success' => true,
            'message' => 'Megrendelés véglegesítve',
            'finalized_at' => now()->toIso8601String(),
        ];
    }
}
