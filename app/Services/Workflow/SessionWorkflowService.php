<?php

namespace App\Services\Workflow;

use App\Models\Album;
use App\Models\EmailEvent;
use App\Models\Photo;
use App\Models\TabloUserProgress;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Legacy WorkSession-based tablo workflow.
 *
 * Kezeli a régi, WorkSession-re épülő munkafolyamatokat:
 * - Child session létrehozás
 * - Photo claiming + FIFO conflict kezelés
 * - Step data lekérés (session alapú)
 * - Finalizáció
 */
class SessionWorkflowService
{
    /**
     * Create child work session (WITHOUT album - album created at completion)
     */
    public function createChildSessionOnly(
        User $user,
        WorkSession $parentSession,
        Album $parentAlbum
    ): WorkSession {
        $childSession = WorkSession::create([
            'name' => "{$parentSession->name} - {$user->name}",
            'parent_work_session_id' => $parentSession->id,
            'is_tablo_mode' => true,
            'max_retouch_photos' => $parentSession->max_retouch_photos,
            'status' => 'active',
        ]);

        $childSession->users()->attach($user->id);
        $childSession->albums()->attach($parentAlbum->id);

        return $childSession;
    }

    /**
     * Save claimed photo IDs to user progress (no photos table write!)
     */
    public function saveClaimedPhotosToProgress(
        User $user,
        WorkSession $parentSession,
        WorkSession $childSession,
        array $photoIds
    ): TabloUserProgress {
        return TabloUserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_session_id' => $parentSession->id,
            ],
            [
                'child_work_session_id' => $childSession->id,
                'current_step' => 'retouch',
                'steps_data' => [
                    'claimed_photo_ids' => $photoIds,
                    'claimed_count' => count($photoIds),
                ],
            ]
        );
    }

    /**
     * Finalize tablo workflow - create child album, copy photos, reserve in parent
     */
    public function finalizeTabloWorkflow(
        User $user,
        WorkSession $childSession,
        Album $parentAlbum,
        array $claimedPhotoIds
    ): array {
        $childAlbum = null;
        $photoIdMapping = [];
        $moved = [];
        $conflicts = [];

        DB::transaction(function () use ($user, $childSession, $parentAlbum, $claimedPhotoIds, &$childAlbum, &$photoIdMapping, &$moved, &$conflicts) {
            $childAlbum = Album::create([
                'title' => "{$parentAlbum->title} - {$user->name}",
                'parent_album_id' => $parentAlbum->id,
                'created_by_user_id' => $user->id,
                'visibility' => 'link',
            ]);

            foreach ($claimedPhotoIds as $photoId) {
                $photo = Photo::lockForUpdate()
                    ->where('id', $photoId)
                    ->where('album_id', $parentAlbum->id)
                    ->whereNull('claimed_by_user_id')
                    ->first();

                if (! $photo) {
                    $conflicts[] = $photoId;

                    continue;
                }

                $newPhoto = $photo->replicate();
                $newPhoto->album_id = $childAlbum->id;
                $newPhoto->assigned_user_id = $user->id;
                $newPhoto->claimed_by_user_id = null;
                $newPhoto->save();

                foreach ($photo->media as $media) {
                    $media->copy($newPhoto, 'photo');
                }

                $photo->update(['claimed_by_user_id' => $user->id]);

                $photoIdMapping[$photoId] = $newPhoto->id;
                $moved[] = $photoId;

                \Log::info('[TabloCompletion] Photo copied to child album and reserved in parent', [
                    'user_id' => $user->id,
                    'old_photo_id' => $photoId,
                    'new_photo_id' => $newPhoto->id,
                    'parent_album_id' => $parentAlbum->id,
                    'child_album_id' => $childAlbum->id,
                ]);
            }

            $childSession->albums()->attach($childAlbum->id);
            $childSession->albums()->detach($parentAlbum->id);

            $parentSession = WorkSession::find($childSession->parent_work_session_id);
            if ($parentSession) {
                $parentSession->users()->detach($user->id);

                \Log::info('[TabloCompletion] User detached from parent session', [
                    'user_id' => $user->id,
                    'parent_session_id' => $parentSession->id,
                    'child_session_id' => $childSession->id,
                ]);
            }

            \Log::info('[TabloCompletion] Parent album detached from child session', [
                'user_id' => $user->id,
                'child_session_id' => $childSession->id,
                'parent_album_id' => $parentAlbum->id,
                'child_album_id' => $childAlbum->id,
            ]);
        });

        return [
            'childAlbum' => $childAlbum,
            'photoIdMapping' => $photoIdMapping,
            'moved' => $moved,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Remove photos from other users' progress if they were claimed by winner
     */
    public function removeConflictingPhotosFromOtherUsers(
        User $winnerUser,
        WorkSession $parentSession,
        array $movedPhotoIds
    ): void {
        if (empty($movedPhotoIds)) {
            return;
        }

        $affectedProgresses = TabloUserProgress::where('work_session_id', $parentSession->id)
            ->where('user_id', '!=', $winnerUser->id)
            ->get()
            ->filter(function ($progress) use ($movedPhotoIds) {
                $claimedIds = $progress->steps_data['claimed_photo_ids'] ?? [];

                return ! empty(array_intersect($claimedIds, $movedPhotoIds));
            });

        foreach ($affectedProgresses as $progress) {
            $claimedIds = $progress->steps_data['claimed_photo_ids'] ?? [];
            $retouchIds = $progress->steps_data['retouch_photo_ids'] ?? [];

            $removedPhotos = array_intersect($claimedIds, $movedPhotoIds);

            if (empty($removedPhotos)) {
                continue;
            }

            $newClaimedIds = array_values(array_diff($claimedIds, $movedPhotoIds));
            $newRetouchIds = array_values(array_diff($retouchIds, $movedPhotoIds));

            $stepsData = $progress->steps_data ?? [];
            $stepsData['claimed_photo_ids'] = $newClaimedIds;
            $stepsData['claimed_count'] = count($newClaimedIds);
            $stepsData['retouch_photo_ids'] = $newRetouchIds;
            $stepsData['retouch_count'] = count($newRetouchIds);

            $progress->update(['steps_data' => $stepsData]);

            \Log::info('[TabloPhotoConflict] Photos removed from user progress', [
                'affected_user_id' => $progress->user->id,
                'affected_user_email' => $progress->user->email,
                'winner_user_id' => $winnerUser->id,
                'removed_photo_ids' => $removedPhotos,
                'removed_count' => count($removedPhotos),
                'remaining_claimed' => count($newClaimedIds),
            ]);

            $this->sendPhotoRemovedEmail($progress->user, $removedPhotos, $winnerUser);
        }
    }

    /**
     * Get step data for session-based workflow
     */
    public function getStepData(User $user, WorkSession $session, string $step): array
    {
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where(function ($q) use ($session) {
                $q->where('work_session_id', $session->id)
                    ->orWhere('child_work_session_id', $session->id);
            })
            ->first();

        $album = $this->getAlbumForStep($user, $session, $step, $progress);
        $visiblePhotos = $this->getVisiblePhotos($album, $step, $progress);
        $selectedPhotos = $this->getSelectedPhotos($step, $progress);
        $metadata = WorkflowStepHelper::getStepMetadata($step, $session->max_retouch_photos);

        return [
            'current_step' => $step,
            'visible_photos' => $visiblePhotos,
            'selected_photos' => $selectedPhotos,
            'step_metadata' => $metadata,
            'album_id' => $album->id,
            'progress' => $progress,
            'work_session' => [
                'id' => $session->id,
                'max_retouch_photos' => $session->max_retouch_photos,
            ],
        ];
    }

    /**
     * Get album for step (parent or child)
     */
    private function getAlbumForStep(User $user, WorkSession $session, string $step, ?TabloUserProgress $progress): Album
    {
        if (in_array($step, ['claiming', 'registration'])) {
            return $session->albums()->first();
        }

        if ($progress && $progress->childWorkSession) {
            $childAlbum = $progress->childWorkSession->albums()->first();
            if ($childAlbum) {
                return $childAlbum;
            }
        }

        return $session->albums()->first();
    }

    /**
     * Get visible photos for step
     */
    private function getVisiblePhotos(Album $album, string $step, ?TabloUserProgress $progress): Collection
    {
        $query = Photo::where('album_id', $album->id);

        if (in_array($step, ['claiming', 'registration'])) {
            return $query->get();
        }

        if ($step === 'retouch') {
            $claimedIds = $progress?->steps_data['claimed_photo_ids'] ?? [];

            return $query->whereIn('id', $claimedIds)->get();
        }

        if ($step === 'tablo') {
            $retouchIds = $progress?->steps_data['retouch_photo_ids'] ?? [];

            return $query->whereIn('id', $retouchIds)->get();
        }

        if ($step === 'completed') {
            return $query->get();
        }

        return collect();
    }

    /**
     * Get pre-selected photos for step
     */
    private function getSelectedPhotos(string $step, ?TabloUserProgress $progress): array
    {
        if (! $progress) {
            return [];
        }

        $stepsData = $progress->steps_data ?? [];

        if ($step === 'claiming') {
            return $stepsData['claimed_photo_ids'] ?? [];
        }

        if ($step === 'retouch') {
            return $stepsData['retouch_photo_ids'] ?? [];
        }

        if ($step === 'tablo') {
            $tabloId = $stepsData['tablo_photo_id'] ?? null;

            return $tabloId ? [$tabloId] : [];
        }

        return [];
    }

    /**
     * Send email to user when their photos were claimed by another user
     */
    private function sendPhotoRemovedEmail(User $user, array $removedPhotoIds, User $winnerUser): void
    {
        \Log::info('[TabloPhotoConflict] Sending email notification', [
            'affected_user_id' => $user->id,
            'affected_user_email' => $user->email,
            'winner_user_id' => $winnerUser->id,
            'removed_photo_ids' => $removedPhotoIds,
            'removed_count' => count($removedPhotoIds),
        ]);

        $emailEvent = EmailEvent::where('event_type', 'tablo_photo_conflict')
            ->where('is_active', true)
            ->first();

        if (! $emailEvent || ! $emailEvent->emailTemplate) {
            \Log::error('[TabloPhotoConflict] Email event or template not found', [
                'event_type' => 'tablo_photo_conflict',
            ]);

            return;
        }

        $emailService = app(EmailService::class);
        $emailVariableService = app(EmailVariableService::class);

        $progress = TabloUserProgress::where('user_id', $user->id)->first();
        $workSession = $progress?->childWorkSession;

        $variables = $emailVariableService->resolveVariables(
            user: $user,
            workSession: $workSession,
            authData: [
                'removed_count' => count($removedPhotoIds),
                'removed_photo_ids' => $removedPhotoIds,
                'winner_user_name' => $winnerUser->name,
            ]
        );

        try {
            $emailService->sendFromTemplate(
                template: $emailEvent->emailTemplate,
                recipientEmail: $user->email,
                variables: $variables,
                recipientUser: $user,
                eventType: 'tablo_photo_conflict'
            );

            \Log::info('[TabloPhotoConflict] Email sent successfully', [
                'recipient' => $user->email,
                'removed_count' => count($removedPhotoIds),
            ]);
        } catch (\Exception $e) {
            \Log::error('[TabloPhotoConflict] Failed to send email', [
                'recipient' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
