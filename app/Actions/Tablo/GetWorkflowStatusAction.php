<?php

namespace App\Actions\Tablo;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;

class GetWorkflowStatusAction
{
    /**
     * Get workflow status for current user
     *
     * @param User $user
     * @param TabloGallery $gallery
     * @return array Workflow status data
     */
    public function execute(User $user, TabloGallery $gallery): array
    {
        // Find user progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Get steps data
        $stepsData = $progress?->steps_data ?? [];

        // Extract claimed and retouch data
        $claimedMediaIds = $stepsData['claimed_media_ids'] ?? [];
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];
        $tabloMediaId = $stepsData['tablo_media_id'] ?? null;

        // Get max_retouch_photos: project → partner → gallery → 5
        $project = $gallery->projects()->first();
        $maxRetouchPhotos = $project
            ? $project->getEffectiveMaxRetouchPhotos()
            : ($gallery->max_retouch_photos ?? 5);

        // Determine workflow status
        $workflowStatus = $progress?->workflow_status ?? TabloUserProgress::STATUS_IN_PROGRESS;
        $currentStep = $progress?->current_step ?? 'claiming';

        // Build validation info
        $validation = $this->buildValidationInfo(
            currentStep: $currentStep,
            claimedMediaIds: $claimedMediaIds,
            retouchMediaIds: $retouchMediaIds,
            tabloMediaId: $tabloMediaId,
            maxRetouchPhotos: $maxRetouchPhotos,
            user: $user
        );

        return [
            'current_step' => $currentStep,
            'workflow_status' => $workflowStatus,
            'max_retouch_photos' => $maxRetouchPhotos,
            'claimed_photos' => count($claimedMediaIds),
            'retouch_photo_ids' => $retouchMediaIds,
            'tablo_photo_id' => $tabloMediaId,
            'validation' => $validation,
            'gallery' => [
                'id' => $gallery->id,
                'name' => $gallery->name,
                'photos_count' => $gallery->photos_count,
            ],
            'is_finalized' => $workflowStatus === TabloUserProgress::STATUS_FINALIZED,
        ];
    }

    /**
     * Build validation info for frontend step navigation
     */
    private function buildValidationInfo(
        string $currentStep,
        array $claimedMediaIds,
        array $retouchMediaIds,
        ?int $tabloMediaId,
        int $maxRetouchPhotos,
        User $user
    ): array {
        $validation = [
            'can_proceed' => false,
            'errors' => [],
            'warnings' => [],
        ];

        $isCustomer = $user->hasRole(User::ROLE_CUSTOMER);

        switch ($currentStep) {
            case 'claiming':
                if (empty($claimedMediaIds)) {
                    $validation['errors'][] = 'Válassz ki legalább egy képet';
                } else {
                    $validation['can_proceed'] = true;
                }
                break;

            case 'registration':
                if ($isCustomer) {
                    $validation['can_proceed'] = true;
                    $validation['warnings'][] = 'Regisztrált felhasználó - lépés kihagyható';
                } else {
                    $validation['errors'][] = 'Regisztráció szükséges a folytatáshoz';
                }
                break;

            case 'retouch':
                if (empty($retouchMediaIds)) {
                    $validation['errors'][] = 'Válassz ki legalább egy retusálandó képet';
                } elseif (count($retouchMediaIds) > $maxRetouchPhotos) {
                    $validation['errors'][] = "Maximum {$maxRetouchPhotos} képet választhatsz retusálásra";
                } else {
                    $validation['can_proceed'] = true;
                }
                break;

            case 'tablo':
                if (!$tabloMediaId) {
                    $validation['errors'][] = 'Válassz ki egy tablóképet';
                } elseif (!empty($retouchMediaIds) && !in_array($tabloMediaId, $retouchMediaIds)) {
                    $validation['errors'][] = 'A tablókép nincs a retusálandó képek között';
                } else {
                    $validation['can_proceed'] = true;
                }
                break;

            case 'completed':
                $validation['can_proceed'] = false;
                $validation['warnings'][] = 'A workflow már véglegesítve lett';
                break;
        }

        return $validation;
    }
}
