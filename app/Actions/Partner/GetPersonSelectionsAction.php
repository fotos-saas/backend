<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGuestSession;
use App\Models\TabloUserProgress;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Egy adott személy kiválasztásainak lekérdezése csoportonként.
 *
 * Visszaadja a saját (claimed), retusált és tablókép kiválasztásokat
 * thumbnail URL-ekkel a partner monitoring nézethez.
 */
class GetPersonSelectionsAction
{
    /**
     * @return array{claimed: array, retouch: array, tablo: array|null, workflowStatus: string|null, currentStep: string|null}
     */
    public function execute(int $projectId, int $galleryId, int $personId): array
    {
        // 1. Guest session keresése a person-höz
        $session = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
            ->where('tablo_person_id', $personId)
            ->first();

        if (!$session || !$session->user_id) {
            return $this->emptyResult();
        }

        // 2. Progress rekord keresése
        $progress = TabloUserProgress::where('user_id', $session->user_id)
            ->where('tablo_gallery_id', $galleryId)
            ->first();

        if (!$progress) {
            return $this->emptyResult();
        }

        $stepsData = $progress->steps_data ?? [];

        // 3. Media ID-k kinyerése
        $claimedIds = $stepsData['claimed_media_ids'] ?? [];
        $retouchIds = $stepsData['retouch_media_ids'] ?? ($progress->retouch_photo_ids ?? []);
        $tabloId = $stepsData['tablo_media_id'] ?? $progress->tablo_photo_id;

        // 4. Összes releváns media ID → egy query-vel betöltés
        $allMediaIds = array_values(array_unique(array_filter(array_merge(
            $claimedIds,
            $retouchIds,
            $tabloId ? [(int) $tabloId] : [],
        ))));

        $mediaCollection = collect();
        if (!empty($allMediaIds)) {
            $mediaCollection = Media::whereIn('id', $allMediaIds)->get()->keyBy('id');
        }

        // 5. Válasz összeállítása
        return [
            'claimed' => $this->buildPhotoList($claimedIds, $mediaCollection),
            'retouch' => $this->buildPhotoList($retouchIds, $mediaCollection),
            'tablo' => $tabloId ? $this->buildPhotoItem((int) $tabloId, $mediaCollection) : null,
            'workflowStatus' => $progress->workflow_status,
            'currentStep' => $progress->current_step,
        ];
    }

    /**
     * @return array<int, array{id: int, thumbUrl: string|null, originalName: string|null}>
     */
    private function buildPhotoList(array $mediaIds, Collection $mediaCollection): array
    {
        $result = [];
        foreach ($mediaIds as $id) {
            $result[] = $this->buildPhotoItem((int) $id, $mediaCollection);
        }

        return $result;
    }

    /**
     * @return array{id: int, thumbUrl: string|null, originalName: string|null}
     */
    private function buildPhotoItem(int $mediaId, Collection $mediaCollection): array
    {
        $media = $mediaCollection->get($mediaId);

        if (!$media) {
            return [
                'id' => $mediaId,
                'thumbUrl' => null,
                'originalName' => null,
            ];
        }

        return [
            'id' => $mediaId,
            'thumbUrl' => $media->getUrl('thumb'),
            'originalName' => $media->file_name,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'claimed' => [],
            'retouch' => [],
            'tablo' => null,
            'workflowStatus' => null,
            'currentStep' => null,
        ];
    }
}
