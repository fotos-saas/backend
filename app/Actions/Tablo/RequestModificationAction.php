<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;

class RequestModificationAction
{
    /**
     * Request modification of finalized workflow.
     * If within free edit window → free. If outside → "payment" (placeholder: always free).
     * Un-finalizes workflow and increments modification count.
     */
    public function execute(User $user, TabloGallery $gallery): array
    {
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if (!$progress) {
            return [
                'success' => false,
                'error' => 'Nincs progress adat ehhez a galériához',
                'status' => 404,
            ];
        }

        if (!$progress->isFinalized()) {
            return [
                'success' => false,
                'error' => 'A workflow nincs véglegesítve',
                'status' => 400,
            ];
        }

        // Resolve free edit window from project → partner → 24h
        $project = $gallery->projects()->first();
        $freeEditHours = $project
            ? $project->getEffectiveFreeEditWindowHours()
            : 24;

        $isFree = $progress->isWithinFreeEditWindow($freeEditHours);

        // If not free → placeholder payment (always succeeds for now)
        if (!$isFree) {
            $progress->last_modification_paid_at = now();
        }

        // Un-finalize: revert to in_progress, clear finalized_at, increment count
        $progress->update([
            'workflow_status' => TabloUserProgress::STATUS_IN_PROGRESS,
            'current_step' => 'claiming',
            'finalized_at' => null,
            'modification_count' => ($progress->modification_count ?? 0) + 1,
            'last_modification_paid_at' => $progress->last_modification_paid_at,
        ]);

        \Log::info('[RequestModificationAction] Workflow un-finalized for modification', [
            'user_id' => $user->id,
            'gallery_id' => $gallery->id,
            'was_free' => $isFree,
            'modification_count' => $progress->modification_count,
        ]);

        return [
            'success' => true,
            'was_free' => $isFree,
            'message' => $isFree
                ? 'Ingyenes módosítás engedélyezve'
                : 'Módosítás engedélyezve (tesztüzem - díjmentes)',
        ];
    }
}
