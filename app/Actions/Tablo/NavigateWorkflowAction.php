<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;
use App\Services\TabloWorkflowService;

class NavigateWorkflowAction
{
    public function __construct(
        private TabloWorkflowService $workflowService
    ) {}

    /**
     * Move to next step in workflow
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @return array Response with step data
     */
    public function nextStep(User $user, TabloGallery $gallery): array
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

        if ($progress->current_step === 'completed') {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett',
                'status' => 403,
            ];
        }

        // Auto-fix legacy data: customer user stuck on 'registration'
        if ($user->hasRole(User::ROLE_CUSTOMER) && $progress->current_step === 'registration') {
            \Log::warning('[NavigateWorkflowAction] Auto-fixing customer user on registration step', [
                'user_id' => $user->id,
                'old_step' => 'registration',
                'new_step' => 'retouch',
            ]);

            $progress->update(['current_step' => 'retouch']);

            $stepData = $this->workflowService->getStepData($user, $gallery, 'retouch');
            $stepData['auto_fixed'] = true;

            return [
                'success' => true,
                'data' => $stepData,
            ];
        }

        // Determine next step
        $nextStep = $this->workflowService->determineNextStep($progress->current_step, $user);

        // Update progress
        $progress->update(['current_step' => $nextStep]);

        return [
            'success' => true,
            'data' => $this->workflowService->getStepData($user, $gallery, $nextStep),
        ];
    }

    /**
     * Move to previous step in workflow
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @return array Response with step data
     */
    public function previousStep(User $user, TabloGallery $gallery): array
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

        if ($progress->current_step === 'completed') {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett, nem lehet visszalépni',
                'status' => 403,
            ];
        }

        if ($progress->current_step === 'claiming') {
            return [
                'success' => false,
                'error' => 'Ez az első lépés, nem lehet visszalépni',
                'status' => 400,
            ];
        }

        // Determine previous step
        $previousStep = $this->workflowService->determinePreviousStep($progress->current_step, $user);

        // Update progress
        $progress->update(['current_step' => $previousStep]);

        return [
            'success' => true,
            'data' => $this->workflowService->getStepData($user, $gallery, $previousStep),
        ];
    }

    /**
     * Move to a specific step
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @param string $targetStep
     * @return array Response with step data
     */
    public function moveToStep(User $user, TabloGallery $gallery, string $targetStep): array
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

        if ($progress->current_step === 'completed') {
            return [
                'success' => false,
                'error' => 'A megrendelés már véglegesítve lett, nem lehet visszalépni',
                'status' => 403,
            ];
        }

        // Update current step
        $progress->update([
            'current_step' => $targetStep,
        ]);

        return [
            'success' => true,
            'data' => $this->workflowService->getStepData($user, $gallery, $targetStep),
        ];
    }
}
