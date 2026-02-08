<?php

namespace App\Services;

use App\Models\Album;
use App\Models\EmailEvent;
use App\Models\Photo;
use App\Models\TabloGallery;
use App\Models\TabloUserProgress;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TabloWorkflowService
{
    /**
     * Create child work session (WITHOUT album - album created at completion)
     *
     * @param  User  $user  User who registered
     * @param  WorkSession  $parentSession  Parent work session
     * @param  Album  $parentAlbum  Parent album (will be attached to child session)
     * @return WorkSession
     */
    public function createChildSessionOnly(
        User $user,
        WorkSession $parentSession,
        Album $parentAlbum
    ): WorkSession {
        // 1. Create child work session
        $childSession = WorkSession::create([
            'name' => "{$parentSession->name} - {$user->name}",
            'parent_work_session_id' => $parentSession->id,
            'is_tablo_mode' => true,
            'max_retouch_photos' => $parentSession->max_retouch_photos,
            'status' => 'active',
        ]);

        // 2. Associate user with child session
        $childSession->users()->attach($user->id);

        // 3. Attach PARENT album to child session (NOT child album!)
        // Child album will be created only at completion
        $childSession->albums()->attach($parentAlbum->id);

        return $childSession;
    }

    /**
     * Save claimed photo IDs to user progress (no photos table write!)
     *
     * @param  User  $user  User who claimed photos
     * @param  WorkSession  $parentSession  Parent work session
     * @param  WorkSession  $childSession  Child work session
     * @param  array<int>  $photoIds  Array of photo IDs
     * @return TabloUserProgress
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
     * Called at workflow completion (véglegesítés)
     * Photos are RESERVED in parent album (NOT deleted!)
     *
     * @param  User  $user  User completing workflow
     * @param  WorkSession  $childSession  Child work session
     * @param  Album  $parentAlbum  Parent album
     * @param  array<int>  $claimedPhotoIds  Array of photo IDs to reserve and copy
     * @return array{childAlbum: Album, photoIdMapping: array<int, int>, moved: array<int>, conflicts: array<int>}
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
            // 1. Create child album
            $childAlbum = Album::create([
                'title' => "{$parentAlbum->title} - {$user->name}",
                'parent_album_id' => $parentAlbum->id,
                'created_by_user_id' => $user->id,
                'visibility' => 'link',
            ]);

            // 2. Copy photos from parent to child album and RESERVE them (FIFO - first to complete wins!)
            // IMPORTANT: Do this BEFORE detaching parent album, so if exception occurs, parent album stays attached
            foreach ($claimedPhotoIds as $photoId) {
                $photo = Photo::lockForUpdate()
                    ->where('id', $photoId)
                    ->where('album_id', $parentAlbum->id)
                    ->whereNull('claimed_by_user_id') // Only unclaimed photos!
                    ->first();

                if (! $photo) {
                    // Photo already claimed by another user (FIFO conflict) OR doesn't exist
                    $conflicts[] = $photoId;

                    continue;
                }

                // Create copy in child album
                $newPhoto = $photo->replicate();
                $newPhoto->album_id = $childAlbum->id;
                $newPhoto->assigned_user_id = $user->id;
                $newPhoto->claimed_by_user_id = null; // New photo is not claimed
                $newPhoto->save();

                // Copy media files
                foreach ($photo->media as $media) {
                    $media->copy($newPhoto, 'photo');
                }

                // RESERVE photo in parent album (NOT delete!)
                $photo->update(['claimed_by_user_id' => $user->id]);

                // Add to mapping
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

            // 3. Associate child album with child session (AFTER photo copy succeeds!)
            $childSession->albums()->attach($childAlbum->id);

            // 4. Detach parent album from child session (ONLY if all photos copied successfully!)
            $childSession->albums()->detach($parentAlbum->id);

            // 5. Detach user from parent session (so user only sees child session albums)
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
     * Sends email notification to affected users
     *
     * @param  User  $winnerUser  User who won the photos (completed first)
     * @param  WorkSession  $parentSession  Parent work session
     * @param  array<int>  $movedPhotoIds  Photo IDs that were moved
     * @return void
     */
    public function removeConflictingPhotosFromOtherUsers(
        User $winnerUser,
        WorkSession $parentSession,
        array $movedPhotoIds
    ): void {
        if (empty($movedPhotoIds)) {
            return;
        }

        // Find all users who have these photos in their claimed list
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

            // Find removed photos
            $removedPhotos = array_intersect($claimedIds, $movedPhotoIds);

            if (empty($removedPhotos)) {
                continue;
            }

            // Remove from claimed list
            $newClaimedIds = array_values(array_diff($claimedIds, $movedPhotoIds));

            // Remove from retouch list (invariant: retouch ⊆ claimed)
            $newRetouchIds = array_values(array_diff($retouchIds, $movedPhotoIds));

            // Update progress
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

            // Send email notification
            $this->sendPhotoRemovedEmail($progress->user, $removedPhotos, $winnerUser);
        }
    }

    /**
     * Send email to user when their photos were claimed by another user
     *
     * @param  User  $user  User who lost photos
     * @param  array<int>  $removedPhotoIds  Photo IDs that were removed
     * @param  User  $winnerUser  User who won the photos
     * @return void
     */
    private function sendPhotoRemovedEmail(User $user, array $removedPhotoIds, User $winnerUser): void
    {
        // Log the conflict
        \Log::info('[TabloPhotoConflict] Sending email notification', [
            'affected_user_id' => $user->id,
            'affected_user_email' => $user->email,
            'winner_user_id' => $winnerUser->id,
            'removed_photo_ids' => $removedPhotoIds,
            'removed_count' => count($removedPhotoIds),
        ]);

        // Get EmailEvent
        $emailEvent = EmailEvent::where('event_type', 'tablo_photo_conflict')
            ->where('is_active', true)
            ->first();

        if (! $emailEvent || ! $emailEvent->emailTemplate) {
            \Log::error('[TabloPhotoConflict] Email event or template not found', [
                'event_type' => 'tablo_photo_conflict',
            ]);

            return;
        }

        // Get services
        $emailService = app(EmailService::class);
        $emailVariableService = app(EmailVariableService::class);

        // Get user's tablo progress to find their work session
        $progress = TabloUserProgress::where('user_id', $user->id)->first();
        $workSession = $progress?->childWorkSession;

        // Resolve variables
        $variables = $emailVariableService->resolveVariables(
            user: $user,
            workSession: $workSession,
            authData: [
                'removed_count' => count($removedPhotoIds),
                'removed_photo_ids' => $removedPhotoIds,
                'winner_user_name' => $winnerUser->name,
            ]
        );

        // Send email
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

    /**
     * Save step progress for user
     *
     * @param  User  $user  User
     * @param  WorkSession  $session  Work session
     * @param  string  $step  Current step name
     * @param  array<string, mixed>  $data  Step-specific data
     */
    public function saveStepProgress(
        User $user,
        WorkSession $session,
        string $step,
        array $data
    ): TabloUserProgress {
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('work_session_id', $session->id)
            ->first();

        // Merge data directly into steps_data (flat structure, no nesting)
        $existingStepsData = $progress->steps_data ?? [];
        $mergedStepsData = array_merge($existingStepsData, $data);

        $progress = TabloUserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_session_id' => $session->id,
            ],
            [
                'current_step' => $step,
                'steps_data' => $mergedStepsData,
            ]
        );

        return $progress;
    }

    /**
     * Save cart comment for user progress
     *
     * @param  User  $user  User
     * @param  WorkSession  $session  Work session
     * @param  string  $comment  Cart comment
     */
    public function saveCartComment(
        User $user,
        WorkSession $session,
        string $comment
    ): TabloUserProgress {
        $progress = TabloUserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_session_id' => $session->id,
            ],
            [
                'cart_comment' => $comment,
            ]
        );

        return $progress;
    }

    /**
     * Get complete step data (photos, selection, metadata) - UNIFIED ENDPOINT
     * Source of truth for step rendering
     *
     * @param  User  $user  Current user
     * @param  WorkSession|TabloGallery  $sessionOrGallery  Work session OR Tablo gallery
     * @param  string  $step  Current step name
     * @return array{current_step: string, visible_photos: Collection, selected_photos: array<int>, step_metadata: array, album_id: int, progress: TabloUserProgress|null, work_session: array}
     */
    public function getStepData(User $user, WorkSession|TabloGallery $sessionOrGallery, string $step): array
    {
        // Determine if this is a gallery-based or session-based workflow
        $isGalleryMode = $sessionOrGallery instanceof TabloGallery;

        if ($isGalleryMode) {
            // GALLERY MODE: New workflow
            return $this->getGalleryStepData($user, $sessionOrGallery, $step);
        }

        // SESSION MODE: Legacy workflow (WorkSession)
        $session = $sessionOrGallery;

        // 1. Load progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where(function ($q) use ($session) {
                $q->where('work_session_id', $session->id)
                    ->orWhere('child_work_session_id', $session->id);
            })
            ->first();

        // 2. Determine album (parent vs child)
        $album = $this->getAlbumForStep($user, $session, $step, $progress);

        // 3. Get visible photos (filtered by step)
        $visiblePhotos = $this->getVisiblePhotos($album, $step, $progress);

        // 4. Get pre-selected photos (from progress)
        $selectedPhotos = $this->getSelectedPhotos($step, $progress);

        // 5. Step metadata
        $metadata = $this->getStepMetadata($session, $step);

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
     * Get step data for GALLERY-BASED workflow
     *
     * @param  User  $user  Current user
     * @param  TabloGallery  $gallery  Tablo gallery
     * @param  string  $step  Current step name
     * @return array{current_step: string, visible_photos: Collection, selected_photos: array<int>, step_metadata: array, album_id: int, progress: TabloUserProgress|null, work_session: array}
     */
    public function getGalleryStepData(User $user, TabloGallery $gallery, string $step): array
    {
        // 1. Load progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // 2. Get visible photos (from gallery media)
        $visiblePhotos = $this->getVisiblePhotosFromGallery($gallery, $step, $progress);

        // 3. Get pre-selected photos (from progress - media IDs!)
        $selectedPhotos = $this->getSelectedPhotosFromGallery($step, $progress);

        // 4. Step metadata (gallery mode - use project-aware max_retouch_photos)
        $maxRetouchPhotos = $this->resolveMaxRetouchPhotos($gallery);
        $metadata = $this->getGalleryStepMetadata($gallery, $step, $maxRetouchPhotos);

        $result = [
            'current_step' => $step,
            'visible_photos' => $visiblePhotos->values()->toArray(),
            'selected_photos' => $selectedPhotos,
            'step_metadata' => $metadata,
            'album_id' => 0, // No album in gallery mode
            'progress' => $progress,
            'work_session' => [
                'id' => $gallery->id, // Return gallery ID for frontend compatibility
                'max_retouch_photos' => $maxRetouchPhotos,
            ],
        ];

        // Completed step: add review groups (all 3 step photos grouped)
        if ($step === 'completed' && $progress) {
            $result['review_groups'] = $this->buildReviewGroups($gallery, $progress);
        }

        return $result;
    }

    /**
     * Get album for step (parent or child)
     *
     * @param  User  $user  Current user
     * @param  WorkSession  $session  Work session
     * @param  string  $step  Current step
     * @param  TabloUserProgress|null  $progress  User progress
     * @return Album
     */
    private function getAlbumForStep(User $user, WorkSession $session, string $step, ?TabloUserProgress $progress): Album
    {
        // CLAIMING/REGISTRATION: parent album
        if (in_array($step, ['claiming', 'registration'])) {
            return $session->albums()->first(); // Parent album
        }

        // RETOUCH/TABLO/COMPLETED: child album (if exists)
        if ($progress && $progress->childWorkSession) {
            $childAlbum = $progress->childWorkSession->albums()->first();
            if ($childAlbum) {
                return $childAlbum;
            }
        }

        // Fallback: parent album
        return $session->albums()->first();
    }

    /**
     * Get visible photos for step
     *
     * @param  Album  $album  Current album
     * @param  string  $step  Current step
     * @param  TabloUserProgress|null  $progress  User progress
     * @return Collection<Photo>
     */
    private function getVisiblePhotos(Album $album, string $step, ?TabloUserProgress $progress): Collection
    {
        $query = Photo::where('album_id', $album->id);

        // CLAIMING/REGISTRATION: all photos
        if (in_array($step, ['claiming', 'registration'])) {
            return $query->get();
        }

        // RETOUCH: claimed photos
        if ($step === 'retouch') {
            $claimedIds = $progress?->steps_data['claimed_photo_ids'] ?? [];

            return $query->whereIn('id', $claimedIds)->get();
        }

        // TABLO: retouch photos
        if ($step === 'tablo') {
            $retouchIds = $progress?->steps_data['retouch_photo_ids'] ?? [];

            return $query->whereIn('id', $retouchIds)->get();
        }

        // COMPLETED: claimed photos (child album)
        if ($step === 'completed') {
            return $query->get(); // All photos in child album
        }

        return collect();
    }

    /**
     * Get pre-selected photos for step
     *
     * @param  string  $step  Current step
     * @param  TabloUserProgress|null  $progress  User progress
     * @return array<int>
     */
    private function getSelectedPhotos(string $step, ?TabloUserProgress $progress): array
    {
        if (! $progress) {
            return [];
        }

        $stepsData = $progress->steps_data ?? [];

        // CLAIMING: pre-load claimed IDs
        if ($step === 'claiming') {
            return $stepsData['claimed_photo_ids'] ?? [];
        }

        // RETOUCH: pre-load retouch IDs
        if ($step === 'retouch') {
            return $stepsData['retouch_photo_ids'] ?? [];
        }

        // TABLO: pre-load tablo photo ID
        if ($step === 'tablo') {
            $tabloId = $stepsData['tablo_photo_id'] ?? null;

            return $tabloId ? [$tabloId] : [];
        }

        return [];
    }

    /**
     * Get step metadata (rules, limits)
     *
     * @param  WorkSession  $session  Work session
     * @param  string  $step  Current step
     * @return array{allow_multiple: bool, max_selection: int|null, description: string}
     */
    private function getStepMetadata(WorkSession $session, string $step): array
    {
        $metadata = [
            'allow_multiple' => true,
            'max_selection' => null,
            'description' => '',
        ];

        // CLAIMING: no limit
        if ($step === 'claiming') {
            $metadata['description'] = 'Válaszd ki saját képeidet';
        }

        // REGISTRATION: no selection
        if ($step === 'registration') {
            $metadata['description'] = 'Regisztráció név és email megadásával';
        }

        // RETOUCH: max limit from session
        if ($step === 'retouch') {
            $metadata['max_selection'] = $session->max_retouch_photos;
            $metadata['description'] = 'Válaszd ki a retusálandó képeket';
        }

        // TABLO: exactly 1
        if ($step === 'tablo') {
            $metadata['allow_multiple'] = false;
            $metadata['max_selection'] = 1;
            $metadata['description'] = 'Válassz egy képet tablóra';
        }

        // COMPLETED: no selection
        if ($step === 'completed') {
            $metadata['description'] = 'Rendelés véglegesítve';
        }

        return $metadata;
    }

    /**
     * Determine next step in workflow with auto-skip logic
     * Customer users skip registration step automatically
     *
     * @param string $currentStep Current workflow step
     * @param User $user Current user
     * @return string Next step name
     */
    public function determineNextStep(string $currentStep, User $user): string
    {
        // Workflow definition (2026+ simplified):
        // ALL users: claiming → retouch → tablo → completed
        // Registration step is SKIPPED for all users (onboarding handles registration)
        // Legacy 'registration' step maps to 'retouch' for backward compatibility

        $nextStepMap = [
            'claiming' => 'retouch',  // Always skip registration (onboarding already done)
            'registration' => 'retouch',  // Legacy support
            'retouch' => 'tablo',
            'tablo' => 'completed',
            'completed' => 'completed', // Terminal state
        ];

        $nextStep = $nextStepMap[$currentStep] ?? null;

        if (!$nextStep) {
            \Log::error('[TabloWorkflow] Invalid current step for nextStep', [
                'current_step' => $currentStep,
                'user_id' => $user->id,
            ]);
            throw new \InvalidArgumentException("Érvénytelen lépés: {$currentStep}");
        }

        return $nextStep;
    }

    /**
     * Determine previous step in workflow with auto-skip logic
     * Registration step is SKIPPED for all users (onboarding handles it)
     *
     * @param string $currentStep Current workflow step
     * @param User $user Current user
     * @return string Previous step name
     */
    public function determinePreviousStep(string $currentStep, User $user): string
    {
        // Workflow definition (2026+ simplified, reverse):
        // ALL users: completed → tablo → retouch → claiming
        // Registration step is SKIPPED (onboarding already handles registration)

        $previousStepMap = [
            'registration' => 'claiming',  // Legacy support
            'retouch' => 'claiming',  // Always skip registration
            'tablo' => 'retouch',
            'completed' => 'tablo',
            'claiming' => 'claiming', // Cannot go back from claiming
        ];

        $previousStep = $previousStepMap[$currentStep] ?? null;

        if (!$previousStep) {
            \Log::error('[TabloWorkflow] Invalid current step for previousStep', [
                'current_step' => $currentStep,
                'user_id' => $user->id,
            ]);
            throw new \InvalidArgumentException("Érvénytelen lépés: {$currentStep}");
        }

        return $previousStep;
    }

    /**
     * Get visible photos from gallery media for a specific step
     *
     * @param  TabloGallery  $gallery  Tablo gallery
     * @param  string  $step  Current step
     * @param  TabloUserProgress|null  $progress  User progress
     * @return Collection<array>
     */
    private function getVisiblePhotosFromGallery(TabloGallery $gallery, string $step, ?TabloUserProgress $progress): Collection
    {
        // Get all media from gallery
        $allMedia = $gallery->getMedia('photos');

        // Format media for frontend
        $formatMedia = function (Media $media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail_url' => $media->getUrl('thumb'),
                'preview_url' => $media->getUrl('preview'),
                'filename' => $media->file_name,
                'size' => $media->human_readable_size,
                'created_at' => $media->created_at->toIso8601String(),
            ];
        };

        // CLAIMING/REGISTRATION: all photos
        if (in_array($step, ['claiming', 'registration'])) {
            return $allMedia->map($formatMedia);
        }

        // RETOUCH: claimed media
        if ($step === 'retouch') {
            $claimedMediaIds = $progress?->steps_data['claimed_media_ids'] ?? [];

            return $allMedia
                ->filter(fn($media) => in_array($media->id, $claimedMediaIds))
                ->map($formatMedia)
                ->values();
        }

        // TABLO: retouch media
        if ($step === 'tablo') {
            $retouchMediaIds = $progress?->steps_data['retouch_media_ids'] ?? [];

            return $allMedia
                ->filter(fn($media) => in_array($media->id, $retouchMediaIds))
                ->map($formatMedia)
                ->values();
        }

        // COMPLETED: claimed media
        if ($step === 'completed') {
            $claimedMediaIds = $progress?->steps_data['claimed_media_ids'] ?? [];

            return $allMedia
                ->filter(fn($media) => in_array($media->id, $claimedMediaIds))
                ->map($formatMedia)
                ->values();
        }

        return collect();
    }

    /**
     * Get pre-selected photos from gallery progress (media IDs)
     *
     * @param  string  $step  Current step
     * @param  TabloUserProgress|null  $progress  User progress
     * @return array<int>
     */
    private function getSelectedPhotosFromGallery(string $step, ?TabloUserProgress $progress): array
    {
        if (! $progress) {
            return [];
        }

        $stepsData = $progress->steps_data ?? [];

        // CLAIMING: pre-load claimed media IDs
        if ($step === 'claiming') {
            return $stepsData['claimed_media_ids'] ?? [];
        }

        // RETOUCH: pre-load retouch media IDs
        if ($step === 'retouch') {
            return $stepsData['retouch_media_ids'] ?? [];
        }

        // TABLO: pre-load tablo media ID
        if ($step === 'tablo') {
            $tabloMediaId = $stepsData['tablo_media_id'] ?? null;

            return $tabloMediaId ? [$tabloMediaId] : [];
        }

        return [];
    }

    /**
     * Get step metadata for gallery-based workflow
     *
     * @param  TabloGallery  $gallery  Tablo gallery
     * @param  string  $step  Current step
     * @return array{allow_multiple: bool, max_selection: int|null, description: string}
     */
    private function getGalleryStepMetadata(TabloGallery $gallery, string $step, ?int $maxRetouchPhotos = null): array
    {
        $metadata = [
            'allow_multiple' => true,
            'max_selection' => null,
            'description' => '',
        ];

        // CLAIMING: no limit
        if ($step === 'claiming') {
            $metadata['description'] = 'Válaszd ki saját képeidet';
        }

        // REGISTRATION: no selection
        if ($step === 'registration') {
            $metadata['description'] = 'Regisztráció név és email megadásával';
        }

        // RETOUCH: limit from project → partner → gallery → 5
        if ($step === 'retouch') {
            $metadata['max_selection'] = $maxRetouchPhotos ?? $this->resolveMaxRetouchPhotos($gallery);
            $metadata['description'] = 'Válaszd ki a retusálandó képeket';
        }

        // TABLO: exactly 1
        if ($step === 'tablo') {
            $metadata['allow_multiple'] = false;
            $metadata['max_selection'] = 1;
            $metadata['description'] = 'Válassz egy képet tablóra';
        }

        // COMPLETED: no selection
        if ($step === 'completed') {
            $metadata['description'] = 'Rendelés véglegesítve';
        }

        return $metadata;
    }

    /**
     * Build review groups for completed step (all 3 steps' photos grouped)
     */
    private function buildReviewGroups(TabloGallery $gallery, TabloUserProgress $progress): array
    {
        $stepsData = $progress->steps_data ?? [];
        $allMedia = $gallery->getMedia('photos');

        $formatMedia = function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumbnail_url' => $media->getUrl('thumb'),
                'preview_url' => $media->getUrl('preview'),
                'filename' => $media->file_name,
            ];
        };

        $claimedIds = $stepsData['claimed_media_ids'] ?? [];
        $retouchIds = $stepsData['retouch_media_ids'] ?? [];
        $tabloId = $stepsData['tablo_media_id'] ?? null;

        return [
            'claiming' => $allMedia->filter(fn($m) => in_array($m->id, $claimedIds))->map($formatMedia)->values()->toArray(),
            'retouch' => $allMedia->filter(fn($m) => in_array($m->id, $retouchIds))->map($formatMedia)->values()->toArray(),
            'tablo' => $tabloId ? $allMedia->filter(fn($m) => $m->id === $tabloId)->map($formatMedia)->values()->toArray() : [],
        ];
    }

    /**
     * Resolve max retouch photos from project → partner → gallery → 5
     */
    private function resolveMaxRetouchPhotos(TabloGallery $gallery): int
    {
        // Keressük meg a galériához tartozó projektet
        $project = $gallery->projects()->first();

        if ($project) {
            return $project->getEffectiveMaxRetouchPhotos();
        }

        // Fallback: galéria → 5
        return $gallery->max_retouch_photos ?? 5;
    }
}
