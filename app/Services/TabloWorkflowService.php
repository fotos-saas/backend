<?php

namespace App\Services;

use App\Models\Album;
use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Workflow\GalleryWorkflowService;
use App\Services\Workflow\SessionWorkflowService;
use Illuminate\Support\Collection;

/**
 * TabloWorkflowService - Facade delegáló
 *
 * Backward compatibility wrapper: minden hívó változatlanul hivatkozhat erre a service-re.
 * A tényleges logikát a SessionWorkflowService és GalleryWorkflowService végzi.
 */
class TabloWorkflowService
{
    public function __construct(
        private readonly SessionWorkflowService $sessionWorkflow,
        private readonly GalleryWorkflowService $galleryWorkflow,
    ) {}

    // ============================================================================
    // SESSION-BASED METHODS (delegálva SessionWorkflowService-nek)
    // ============================================================================

    public function createChildSessionOnly(
        User $user,
        WorkSession $parentSession,
        Album $parentAlbum
    ): WorkSession {
        return $this->sessionWorkflow->createChildSessionOnly($user, $parentSession, $parentAlbum);
    }

    public function saveClaimedPhotosToProgress(
        User $user,
        WorkSession $parentSession,
        WorkSession $childSession,
        array $photoIds
    ): TabloUserProgress {
        return $this->sessionWorkflow->saveClaimedPhotosToProgress($user, $parentSession, $childSession, $photoIds);
    }

    public function finalizeTabloWorkflow(
        User $user,
        WorkSession $childSession,
        Album $parentAlbum,
        array $claimedPhotoIds
    ): array {
        return $this->sessionWorkflow->finalizeTabloWorkflow($user, $childSession, $parentAlbum, $claimedPhotoIds);
    }

    public function removeConflictingPhotosFromOtherUsers(
        User $winnerUser,
        WorkSession $parentSession,
        array $movedPhotoIds
    ): void {
        $this->sessionWorkflow->removeConflictingPhotosFromOtherUsers($winnerUser, $parentSession, $movedPhotoIds);
    }

    // ============================================================================
    // SHARED METHODS
    // ============================================================================

    public function saveStepProgress(
        User $user,
        WorkSession $session,
        string $step,
        array $data
    ): TabloUserProgress {
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('work_session_id', $session->id)
            ->first();

        $existingStepsData = $progress->steps_data ?? [];
        $mergedStepsData = array_merge($existingStepsData, $data);

        return TabloUserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_session_id' => $session->id,
            ],
            [
                'current_step' => $step,
                'steps_data' => $mergedStepsData,
            ]
        );
    }

    public function saveCartComment(
        User $user,
        WorkSession $session,
        string $comment
    ): TabloUserProgress {
        return TabloUserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_session_id' => $session->id,
            ],
            [
                'cart_comment' => $comment,
            ]
        );
    }

    // ============================================================================
    // UNIFIED STEP DATA (router - session vs gallery)
    // ============================================================================

    public function getStepData(User $user, WorkSession|TabloGallery $sessionOrGallery, string $step): array
    {
        if ($sessionOrGallery instanceof TabloGallery) {
            return $this->galleryWorkflow->getStepData($user, $sessionOrGallery, $step);
        }

        return $this->sessionWorkflow->getStepData($user, $sessionOrGallery, $step);
    }

    /**
     * @deprecated Use getStepData() with TabloGallery parameter instead
     */
    public function getGalleryStepData(User $user, TabloGallery $gallery, string $step): array
    {
        return $this->galleryWorkflow->getStepData($user, $gallery, $step);
    }

    // ============================================================================
    // NAVIGATION
    // ============================================================================

    public function determineNextStep(string $currentStep, User $user): string
    {
        $nextStepMap = [
            'claiming' => 'retouch',
            'registration' => 'retouch',
            'retouch' => 'tablo',
            'tablo' => 'completed',
            'completed' => 'completed',
        ];

        $nextStep = $nextStepMap[$currentStep] ?? null;

        if (! $nextStep) {
            \Log::error('[TabloWorkflow] Invalid current step for nextStep', [
                'current_step' => $currentStep,
                'user_id' => $user->id,
            ]);
            throw new \InvalidArgumentException("Érvénytelen lépés: {$currentStep}");
        }

        return $nextStep;
    }

    public function determinePreviousStep(string $currentStep, User $user): string
    {
        $previousStepMap = [
            'registration' => 'claiming',
            'retouch' => 'claiming',
            'tablo' => 'retouch',
            'completed' => 'tablo',
            'claiming' => 'claiming',
        ];

        $previousStep = $previousStepMap[$currentStep] ?? null;

        if (! $previousStep) {
            \Log::error('[TabloWorkflow] Invalid current step for previousStep', [
                'current_step' => $currentStep,
                'user_id' => $user->id,
            ]);
            throw new \InvalidArgumentException("Érvénytelen lépés: {$currentStep}");
        }

        return $previousStep;
    }
}
