<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGuestSession;
use App\Models\TabloPerson;
use App\Models\TabloUserProgress;
use Illuminate\Support\Collection;

/**
 * Galéria monitoring adatok összeállítása.
 *
 * Összepárosítja a projekt személyeit (TabloPerson) a workflow progress adatokkal
 * (TabloUserProgress) a guest session-ökön keresztül.
 *
 * Összekapcsolási lánc:
 *   TabloPerson.id → TabloGuestSession.tablo_person_id
 *   TabloGuestSession.user_id → TabloUserProgress.user_id
 */
class GetGalleryMonitoringAction
{
    /** Napok száma, amennyi inaktivitás után stale warning jelenik meg */
    private const STALE_DAYS = 5;

    /**
     * @return array{persons: array, summary: array}
     */
    public function execute(int $projectId, int $galleryId): array
    {
        // 1. Projekt személyek
        $persons = TabloPerson::where('tablo_project_id', $projectId)
            ->orderBy('name')
            ->get();

        // 2. Verified guest session-ök person_id alapján keyelve
        $guestSessions = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
            ->whereNotNull('tablo_person_id')
            ->get()
            ->keyBy('tablo_person_id');

        // 3. Gallery progress rekordok user_id alapján keyelve
        $progressRecords = TabloUserProgress::where('tablo_gallery_id', $galleryId)
            ->get()
            ->keyBy('user_id');

        // 4. Összepárosítás: person → session (person_id) → progress (user_id)
        $personData = $persons->map(function (TabloPerson $person) use ($guestSessions, $progressRecords) {
            return $this->buildPersonData($person, $guestSessions, $progressRecords);
        });

        // 5. Összefoglaló statisztika
        $summary = $this->buildSummary($personData);

        return [
            'persons' => $personData->values()->toArray(),
            'summary' => $summary,
        ];
    }

    private function buildPersonData(
        TabloPerson $person,
        Collection $guestSessions,
        Collection $progressRecords,
    ): array {
        $session = $guestSessions->get($person->id);
        $progress = null;

        // Session → user_id → progress (közvetlen FK kötés)
        if ($session && $session->user_id) {
            $progress = $progressRecords->get($session->user_id);
        }

        $lastActivity = $session?->last_activity_at;
        $daysSinceLastActivity = $lastActivity ? (int) $lastActivity->diffInDays(now()) : null;

        $currentStep = $progress?->current_step;
        $workflowStatus = $progress?->workflow_status;
        $retouchPhotoIds = $progress?->retouch_photo_ids ?? [];
        $tabloPhotoId = $progress?->tablo_photo_id;
        $finalizedAt = $progress?->finalized_at;

        // Stale warning: >5 napja inaktív és nem véglegesített
        $staleWarning = false;
        if ($daysSinceLastActivity !== null
            && $daysSinceLastActivity >= self::STALE_DAYS
            && $workflowStatus !== TabloUserProgress::STATUS_FINALIZED
            && $session !== null
        ) {
            $staleWarning = true;
        }

        return [
            'personId' => $person->id,
            'name' => $person->name,
            'type' => $person->type,
            'hasOpened' => $session !== null,
            'lastActivityAt' => $lastActivity?->toIso8601String(),
            'currentStep' => $currentStep,
            'workflowStatus' => $workflowStatus,
            'retouchCount' => count($retouchPhotoIds),
            'hasTabloPhoto' => $tabloPhotoId !== null,
            'finalizedAt' => $finalizedAt?->toIso8601String(),
            'daysSinceLastActivity' => $daysSinceLastActivity,
            'staleWarning' => $staleWarning,
        ];
    }

    /**
     * @param Collection<int, array> $personData
     */
    private function buildSummary(Collection $personData): array
    {
        $total = $personData->count();
        $opened = $personData->where('hasOpened', true)->count();
        $finalized = $personData->where('workflowStatus', TabloUserProgress::STATUS_FINALIZED)->count();
        $inProgress = $personData->where('workflowStatus', TabloUserProgress::STATUS_IN_PROGRESS)->count();
        $staleCount = $personData->where('staleWarning', true)->count();

        return [
            'totalPersons' => $total,
            'opened' => $opened,
            'notOpened' => $total - $opened,
            'finalized' => $finalized,
            'inProgress' => $inProgress,
            'staleCount' => $staleCount,
        ];
    }
}
