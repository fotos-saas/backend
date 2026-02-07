<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloUserProgress;

/**
 * Galéria haladás statisztika lekérdezése (diák workflow állapotok).
 */
class GetGalleryProgressAction
{
    /**
     * Haladás statisztika összeállítása a galériához.
     *
     * @param int $galleryId
     * @return array{totalUsers: int, claiming: int, retouch: int, tablo: int, completed: int, finalized: int}
     */
    public function execute(int $galleryId): array
    {
        $progressRecords = TabloUserProgress::where('tablo_gallery_id', $galleryId)->get();

        $total = $progressRecords->count();
        $finalized = $progressRecords->where('workflow_status', TabloUserProgress::STATUS_FINALIZED)->count();
        $inProgress = $progressRecords->where('workflow_status', TabloUserProgress::STATUS_IN_PROGRESS);

        $stepCounts = [
            'claiming' => 0,
            'retouch' => 0,
            'tablo' => 0,
            'completed' => 0,
        ];

        foreach ($inProgress as $record) {
            $step = $record->current_step ?? 'claiming';
            if (isset($stepCounts[$step])) {
                $stepCounts[$step]++;
            }
        }

        return [
            'totalUsers' => $total,
            'claiming' => $stepCounts['claiming'],
            'retouch' => $stepCounts['retouch'],
            'tablo' => $stepCounts['tablo'],
            'completed' => $stepCounts['completed'],
            'finalized' => $finalized,
        ];
    }
}
