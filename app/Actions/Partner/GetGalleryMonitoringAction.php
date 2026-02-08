<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloGuestSession;
use App\Models\TabloPerson;
use App\Models\TabloUserProgress;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Galéria monitoring adatok összeállítása.
 *
 * Összepárosítja a projekt személyeit (TabloPerson) a workflow progress adatokkal
 * (TabloUserProgress) a guest session-ökön keresztül.
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
        // 1. Projekt személyek a verified guest session-ökkel
        $persons = TabloPerson::where('tablo_project_id', $projectId)
            ->with(['guestSession'])
            ->orderBy('name')
            ->get();

        // 2. Gallery progress rekordok user_id-vel
        $progressRecords = TabloUserProgress::where('tablo_gallery_id', $galleryId)
            ->with('user')
            ->get()
            ->keyBy('user_id');

        // 3. Guest session → user_id mapping (verified session-ök a projektből)
        $guestSessions = TabloGuestSession::where('tablo_project_id', $projectId)
            ->where('verification_status', TabloGuestSession::VERIFICATION_VERIFIED)
            ->whereNotNull('tablo_person_id')
            ->get()
            ->keyBy('tablo_person_id');

        // 4. Összepárosítás: person → guestSession → user → progress
        $personData = $persons->map(function (TabloPerson $person) use ($progressRecords, $guestSessions) {
            return $this->buildPersonData($person, $progressRecords, $guestSessions);
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
        Collection $progressRecords,
        Collection $guestSessions,
    ): array {
        $session = $guestSessions->get($person->id);
        $progress = null;

        // Ha van session, keressük meg a user progress-t
        if ($session) {
            // A guest session-höz tartozó user_id kell a progress összekapcsoláshoz
            // A TabloGuestSession-ből nincs közvetlen user_id, de a login controller
            // összeköti: User regisztrál → guest session → tablo_person_id
            // A progress-t user_id alapján keressük, ami a session-ből jön
            // Workaround: keresés a progressRecords-ban user-en keresztül
            foreach ($progressRecords as $pr) {
                if ($pr->user && $pr->user->name === $session->guest_name) {
                    $progress = $pr;
                    break;
                }
                // Email-alapú egyeztetés is
                if ($session->guest_email && $pr->user && $pr->user->email === $session->guest_email) {
                    $progress = $pr;
                    break;
                }
            }
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
