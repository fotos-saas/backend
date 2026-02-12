<?php

namespace App\Services\Workflow;

use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Gallery-based tablo workflow service.
 *
 * Kezeli az új, TabloGallery-re épülő munkafolyamatokat:
 * - Gallery step data lekérés
 * - Visible/selected photos from gallery media
 * - Review groups + modification info
 */
class GalleryWorkflowService
{
    /**
     * Get step data for gallery-based workflow
     */
    public function getStepData(User $user, TabloGallery $gallery, string $step): array
    {
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        $visiblePhotos = $this->getVisiblePhotosFromGallery($gallery, $step, $progress);
        $selectedPhotos = $this->getSelectedPhotosFromGallery($step, $progress);

        $maxRetouchPhotos = $this->resolveMaxRetouchPhotos($gallery);
        $metadata = WorkflowStepHelper::getStepMetadata($step, $maxRetouchPhotos);

        $result = [
            'current_step' => $step,
            'visible_photos' => $visiblePhotos->values()->toArray(),
            'selected_photos' => $selectedPhotos,
            'step_metadata' => $metadata,
            'album_id' => 0,
            'progress' => $progress,
            'work_session' => [
                'id' => $gallery->id,
                'max_retouch_photos' => $maxRetouchPhotos,
            ],
        ];

        if ($step === 'completed' && $progress) {
            $result['review_groups'] = $this->buildReviewGroups($gallery, $progress);

            $project = $gallery->projects()->first();
            $partner = $project?->partner;
            $billingEnabled = $partner?->billing_enabled ?? false;
            $freeEditHours = $project
                ? $project->getEffectiveFreeEditWindowHours()
                : 24;

            $isWithinFreeWindow = $billingEnabled
                ? $progress->isWithinFreeEditWindow($freeEditHours)
                : true;

            $result['modification_info'] = [
                'billing_enabled' => $billingEnabled,
                'free_edit_window_hours' => $freeEditHours,
                'finalized_at' => $progress->finalized_at?->toIso8601String(),
                'is_within_free_window' => $isWithinFreeWindow,
                'remaining_seconds' => $billingEnabled
                    ? $progress->getFreeEditRemainingSeconds($freeEditHours)
                    : 0,
                'modification_count' => $progress->modification_count ?? 0,
                'requires_payment' => $billingEnabled && ! $isWithinFreeWindow,
            ];
        }

        return $result;
    }

    /**
     * Get visible photos from gallery media for a specific step
     */
    private function getVisiblePhotosFromGallery(TabloGallery $gallery, string $step, ?TabloUserProgress $progress): Collection
    {
        $allMedia = $gallery->getMedia('photos');

        if (in_array($step, ['claiming', 'registration'])) {
            return $allMedia->map([WorkflowStepHelper::class, 'formatMedia']);
        }

        if ($step === 'retouch') {
            $claimedMediaIds = $progress?->steps_data['claimed_media_ids'] ?? [];

            return $allMedia
                ->filter(fn ($media) => in_array($media->id, $claimedMediaIds))
                ->map([WorkflowStepHelper::class, 'formatMedia'])
                ->values();
        }

        if ($step === 'tablo') {
            $retouchMediaIds = $progress?->steps_data['retouch_media_ids'] ?? [];

            return $allMedia
                ->filter(fn ($media) => in_array($media->id, $retouchMediaIds))
                ->map([WorkflowStepHelper::class, 'formatMedia'])
                ->values();
        }

        if ($step === 'completed') {
            $claimedMediaIds = $progress?->steps_data['claimed_media_ids'] ?? [];

            return $allMedia
                ->filter(fn ($media) => in_array($media->id, $claimedMediaIds))
                ->map([WorkflowStepHelper::class, 'formatMedia'])
                ->values();
        }

        return collect();
    }

    /**
     * Get pre-selected photos from gallery progress (media IDs)
     */
    private function getSelectedPhotosFromGallery(string $step, ?TabloUserProgress $progress): array
    {
        if (! $progress) {
            return [];
        }

        $stepsData = $progress->steps_data ?? [];

        if ($step === 'claiming') {
            return $stepsData['claimed_media_ids'] ?? [];
        }

        if ($step === 'retouch') {
            return $stepsData['retouch_media_ids'] ?? [];
        }

        if ($step === 'tablo') {
            $tabloMediaId = $stepsData['tablo_media_id'] ?? null;

            return $tabloMediaId ? [$tabloMediaId] : [];
        }

        return [];
    }

    /**
     * Build review groups for completed step (all 3 steps' photos grouped)
     */
    private function buildReviewGroups(TabloGallery $gallery, TabloUserProgress $progress): array
    {
        $stepsData = $progress->steps_data ?? [];
        $allMedia = $gallery->getMedia('photos');

        $claimedIds = $stepsData['claimed_media_ids'] ?? [];
        $retouchIds = $stepsData['retouch_media_ids'] ?? [];
        $tabloId = $stepsData['tablo_media_id'] ?? null;

        return [
            'claiming' => $allMedia->filter(fn ($m) => in_array($m->id, $claimedIds))->map([WorkflowStepHelper::class, 'formatMediaForReview'])->values()->toArray(),
            'retouch' => $allMedia->filter(fn ($m) => in_array($m->id, $retouchIds))->map([WorkflowStepHelper::class, 'formatMediaForReview'])->values()->toArray(),
            'tablo' => $tabloId ? $allMedia->filter(fn ($m) => $m->id === $tabloId)->map([WorkflowStepHelper::class, 'formatMediaForReview'])->values()->toArray() : [],
        ];
    }

    /**
     * Resolve max retouch photos from project -> partner -> gallery -> 5
     */
    private function resolveMaxRetouchPhotos(TabloGallery $gallery): int
    {
        $project = $gallery->projects()->first();

        if ($project) {
            return $project->getEffectiveMaxRetouchPhotos();
        }

        return $gallery->max_retouch_photos ?? 5;
    }
}
