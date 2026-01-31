<?php

namespace App\Http\Controllers\Api;

use App\Events\TabloUserRegistered;
use App\Events\TabloWorkflowCompleted;
use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Photo;
use App\Models\TabloGallery;
use App\Models\TabloRegistration;
use App\Models\TabloUserProgress;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\MagicLinkService;
use App\Services\TabloWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TabloWorkflowController extends Controller
{
    /**
     * TabloWorkflowService instance
     */
    public function __construct(
        private TabloWorkflowService $workflowService,
        private MagicLinkService $magicLinkService
    ) {}

    /**
     * Register user from guest and initialize tablo workflow
     */
    public function registerAndInitialize(Request $request)
    {
        // DEBUG: Log incoming request
        \Log::info('🔍 [TabloRegistration] Request received', [
            'headers' => [
                'Authorization' => $request->header('Authorization') ? 'Bearer ***' : 'MISSING',
                'X-Session-Token' => $request->header('X-Session-Token') ?? 'MISSING',
            ],
            'payload' => [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'workSessionId' => $request->input('workSessionId'),
                'albumId' => $request->input('albumId'),
                'claimedPhotoIds_count' => count($request->input('claimedPhotoIds', [])),
            ],
        ]);

        // Get authenticated user (must be logged in via Sanctum)
        $user = $request->user();

        // DEBUG: Log authentication result
        if ($user) {
            \Log::info('✅ [TabloRegistration] User authenticated', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_type' => $user->type ?? 'unknown',
                'guest_token' => $user->guest_token ? substr($user->guest_token, 0, 15) . '...' : 'NULL',
                'roles' => $user->roles->pluck('name')->toArray(),
                'has_guest_role' => $user->hasRole(User::ROLE_GUEST),
            ]);
        } else {
            \Log::error('❌ [TabloRegistration] User NOT authenticated', [
                'auth_check' => auth()->check(),
                'sanctum_guard' => auth('sanctum')->check(),
            ]);
        }

        // User must be authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Nincs bejelentkezett felhasználó. Kérlek frissítsd az oldalt és próbáld újra.',
            ], 401);
        }

        \Log::info('✅ [TabloRegistration] Proceeding with registration for user', [
            'user_id' => $user->id,
        ]);

        // For guest users converting to registered, exclude their own email from unique check
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'workSessionId' => 'required|exists:work_sessions,id',
            'albumId' => 'required|exists:albums,id',
            'claimedPhotoIds' => 'required|array',
            'claimedPhotoIds.*' => 'exists:photos,id',
            'schoolClassId' => 'nullable|exists:classes,id',
        ]);

        $parentSession = WorkSession::findOrFail($validated['workSessionId']);
        $parentAlbum = Album::findOrFail($validated['albumId']);

        // Verify parent session is in tablo mode
        if (! $parentSession->is_tablo_mode) {
            return response()->json([
                'message' => 'A munkamenet nem tablófotózási módban van',
            ], 400);
        }

        // Update guest user → registered user
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'guest_token' => null, // Clear guest token after registration
        ]);

        // Remove ROLE_GUEST, assign ROLE_CUSTOMER
        if ($user->hasRole(User::ROLE_GUEST)) {
            $user->removeRole(User::ROLE_GUEST);
        }
        if (! $user->hasRole(User::ROLE_CUSTOMER)) {
            $user->assignRole(User::ROLE_CUSTOMER);
        }

        // Create TabloRegistration record
        TabloRegistration::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_session_id' => $parentSession->id,
            ],
            [
                'album_id' => $parentAlbum->id,
                'school_class_id' => $validated['schoolClassId'] ?? null,
                'registered_at' => now(),
            ]
        );

        // Create child session (WITHOUT album - album created at completion)
        $childSession = $this->workflowService->createChildSessionOnly(
            $user,
            $parentSession,
            $parentAlbum
        );

        // Save claimed photos to progress (NO photos table write!)
        $progress = $this->workflowService->saveClaimedPhotosToProgress(
            $user,
            $parentSession,
            $childSession,
            $validated['claimedPhotoIds']
        );

        // Generate magic link for user login
        $magicLinkData = $this->magicLinkService->generate($user, 72); // 72 hours validity

        // Dispatch event to send welcome email with magic link
        event(new TabloUserRegistered(
            user: $user,
            childWorkSession: $childSession,
            childAlbum: $parentAlbum, // Parent album (child album created at completion)
            parentWorkSession: $parentSession,
            magicLink: $magicLinkData['url']
        ));

        // Revoke all old tokens (including guest tokens)
        $user->tokens()->delete();

        // Generate NEW authentication token for registered user
        $token = $user->createToken('tablo-flow')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->hasRole(User::ROLE_CUSTOMER) ? 'customer' : 'registered',
                'workSessionId' => $childSession->id,
                'workSessionName' => $childSession->name,
            ],
            'token' => $token,
            'childSession' => $childSession,
            'parentAlbumId' => $parentAlbum->id, // CRITICAL: Frontend needs this to load photos!
            'progress' => $progress,
        ]);
    }

    /**
     * Save claiming selection (photos user claimed as their own)
     * Accepts media IDs from TabloGallery (for gallery-based workflow)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function saveClaimingSelection(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID (param name kept for frontend compatibility)
            'photoIds' => 'present|array', // Can be empty (deselect all)
            'photoIds.*' => 'exists:media,id', // Gallery media IDs
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoIds.present' => 'A képek listája kötelező.',
            'photoIds.array' => 'A képek listája érvénytelen formátumú.',
            'photoIds.*.exists' => 'A kiválasztott kép nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress for this gallery
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already completed
        if ($progress && $progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // Find or create progress record
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'claiming',
                'steps_data' => [],
            ]
        );

        // Save media IDs (gallery photo IDs) directly
        // These will be converted to photos.id later during workflow finalization
        $stepsData = $progress->steps_data ?? [];
        $claimedSet = $validated['photoIds'];

        // Track cascade deletions for frontend notification
        $cascadeDeleted = [
            'retouch' => [],
            'tablo' => false,
        ];

        // IMPORTANT: Cascade delete - clean retouch_media_ids
        // Invariant: retouch_media_ids ⊆ claimed_media_ids
        if (isset($stepsData['retouch_media_ids']) && is_array($stepsData['retouch_media_ids'])) {
            $retouchIds = $stepsData['retouch_media_ids'];

            // Filter: only keep retouch IDs that are in the new claimed set
            $cleanedRetouchIds = array_values(array_intersect($retouchIds, $claimedSet));

            // Track removed photos
            $removedFromRetouch = array_values(array_diff($retouchIds, $cleanedRetouchIds));
            if (!empty($removedFromRetouch)) {
                $cascadeDeleted['retouch'] = $removedFromRetouch;
            }

            $stepsData['retouch_media_ids'] = $cleanedRetouchIds;
            $stepsData['retouch_count'] = count($cleanedRetouchIds);
        }

        // IMPORTANT: Cascade delete - clean tablo_media_id
        // Invariant: tablo_media_id ∈ retouch_media_ids (if retouch was used)
        // OR: tablo_media_id ∈ claimed_media_ids (if no retouch)
        if (isset($stepsData['tablo_media_id'])) {
            $tabloMediaId = $stepsData['tablo_media_id'];
            $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];

            // If retouch is set, tablo must be in retouch; otherwise must be in claimed
            $validTabloSet = !empty($retouchMediaIds) ? $retouchMediaIds : $claimedSet;

            if (!in_array($tabloMediaId, $validTabloSet)) {
                $cascadeDeleted['tablo'] = true;
                unset($stepsData['tablo_media_id']);
            }
        }

        $stepsData['claimed_media_ids'] = $claimedSet;
        $stepsData['claimed_count'] = count($claimedSet);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        // Build response with cascade info
        $response = ['message' => 'Képválasztás mentve'];

        // Add cascade deletion info if any photos were removed from later steps
        $hasCascade = !empty($cascadeDeleted['retouch']) || $cascadeDeleted['tablo'];
        if ($hasCascade) {
            $response['cascade_deleted'] = $cascadeDeleted;
            $response['cascade_message'] = $this->buildCascadeMessage($cascadeDeleted);
        }

        return response()->json($response);
    }

    /**
     * Auto-save retouch selection (without changing step)
     * Called on every photo selection for background persistence
     * Accepts media IDs from TabloGallery (for gallery-based workflow)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function autoSaveRetouchSelection(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
            'photoIds' => 'present|array', // Can be empty (deselect all)
            'photoIds.*' => 'exists:media,id', // Gallery media IDs
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoIds.present' => 'A képek listája kötelező.',
            'photoIds.array' => 'A képek listája érvénytelen formátumú.',
            'photoIds.*.exists' => 'A kiválasztott kép nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress for this gallery
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already completed
        if ($progress && $progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // Find or create progress record
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'retouch',
                'steps_data' => [],
            ]
        );

        // Update steps_data with retouch media IDs (without changing step)
        $stepsData = $progress->steps_data ?? [];

        // VALIDATION: Ensure all retouch photos are claimed photos
        // Invariant: retouch_media_ids ⊆ claimed_media_ids
        $claimedMediaIds = $stepsData['claimed_media_ids'] ?? [];
        $requestedRetouchIds = $validated['photoIds'];

        // Filter: only keep retouch IDs that are in claimed set
        $validRetouchIds = array_values(array_intersect($requestedRetouchIds, $claimedMediaIds));

        // If some photos were filtered out, log it (but don't fail - auto-clean)
        if (count($validRetouchIds) < count($requestedRetouchIds)) {
            $filtered = array_diff($requestedRetouchIds, $validRetouchIds);
            \Log::warning('[autoSaveRetouchSelection] Filtered non-claimed photos from retouch', [
                'user_id' => $user->id,
                'filtered_ids' => $filtered,
                'requested' => $requestedRetouchIds,
                'valid' => $validRetouchIds,
            ]);
        }

        // Track cascade deletions for frontend notification
        $cascadeDeleted = [
            'tablo' => false,
        ];

        // IMPORTANT: Cascade delete - clean tablo_media_id if not in new retouch set
        // Invariant: tablo_media_id ∈ retouch_media_ids
        if (isset($stepsData['tablo_media_id'])) {
            $tabloMediaId = $stepsData['tablo_media_id'];

            if (!in_array($tabloMediaId, $validRetouchIds)) {
                $cascadeDeleted['tablo'] = true;
                unset($stepsData['tablo_media_id']);
            }
        }

        $stepsData['retouch_media_ids'] = $validRetouchIds;
        $stepsData['retouch_count'] = count($validRetouchIds);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        // Build response with cascade info
        $response = ['message' => 'Retusálási választás mentve'];

        // Add cascade deletion info if tablo was removed
        if ($cascadeDeleted['tablo']) {
            $response['cascade_deleted'] = $cascadeDeleted;
            $response['cascade_message'] = 'A tablóképed frissítve lett';
        }

        return response()->json($response);
    }

    /**
     * Save retouch selection and proceed to tablo step
     * Accepts media IDs from TabloGallery (for gallery-based workflow)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function saveRetouchSelection(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
            'photoIds' => 'present|array', // Can be empty
            'photoIds.*' => 'exists:media,id', // Gallery media IDs
            'comments' => 'nullable|array',
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoIds.present' => 'A képek listája kötelező.',
            'photoIds.array' => 'A képek listája érvénytelen formátumú.',
            'photoIds.*.exists' => 'A kiválasztott kép nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress for this gallery
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already completed
        if ($progress && $progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // TODO: Validate max retouch photos limit (needs to be stored in TabloProject or TabloGallery)
        // For now, skip this validation

        // Save comments if provided
        if (! empty($validated['comments'])) {
            foreach ($validated['comments'] as $photoId => $comment) {
                Photo::where('id', $photoId)
                    ->where('assigned_user_id', $user->id)
                    ->update(['user_comment' => $comment]);
            }
        }

        // DEPRECATED: This endpoint should not be used anymore
        // Frontend should use autoSaveRetouchSelection() + moveTabloStep() instead
        // We still save the data but NOT as 'tablo' step (which was wrong)

        // Find or update progress record
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'tablo',
                'steps_data' => [],
            ]
        );

        // Update steps_data with retouch data and move to tablo step
        $stepsData = $progress->steps_data ?? [];
        $stepsData['retouch_media_ids'] = $validated['photoIds']; // Store media IDs
        $stepsData['retouch_count'] = count($validated['photoIds']);

        $progress->update([
            'current_step' => 'tablo',
            'steps_data' => $stepsData,
        ]);

        return response()->json(['message' => 'Retusálási választás mentve']);
    }

    /**
     * Auto-save tablo photo selection (without completing workflow)
     * Called on every photo selection for background persistence
     * Accepts media ID from TabloGallery (for gallery-based workflow)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function autoSaveTabloSelection(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
            'photoId' => 'required|exists:media,id', // Gallery media ID
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress for this gallery
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already completed
        if ($progress && $progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // Find or create progress record FIRST (needed for validation)
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'tablo',
                'steps_data' => [],
            ]
        );

        // Update steps_data with tablo media ID (without changing step)
        $stepsData = $progress->steps_data ?? [];

        // VALIDATION: Ensure tablo photo is in retouch photos (if retouch step was used)
        // Invariant: tablo_media_id ∈ retouch_media_ids (if retouch was used)
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];

        // Check if photo is in retouch list (primary check for tablo workflow)
        if (!empty($retouchMediaIds) && !in_array($validated['photoId'], $retouchMediaIds)) {
            \Log::warning('[autoSaveTabloSelection] Selected tablo photo not in retouch list', [
                'user_id' => $user->id,
                'media_id' => $validated['photoId'],
                'retouch_media_ids' => $retouchMediaIds,
            ]);

            return response()->json([
                'message' => 'A kiválasztott kép nincs a retusálandó képek között',
            ], 400);
        }

        $stepsData['tablo_media_id'] = $validated['photoId']; // Store media ID

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        return response()->json(['message' => 'Tablókép választás mentve']);
    }

    /**
     * Clear tablo photo selection (sets tablo_media_id to null)
     * Called when user deselects the tablo photo
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function clearTabloSelection(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress for this gallery
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if (!$progress) {
            return response()->json(['message' => 'Nincs mentett haladás'], 404);
        }

        // Check if workflow is already completed
        if ($progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // Remove tablo_media_id from steps_data
        $stepsData = $progress->steps_data ?? [];
        unset($stepsData['tablo_media_id']);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        return response()->json(['message' => 'Tablókép kijelölés törölve']);
    }

    /**
     * Save tablo photo selection (final step)
     * Accepts media ID from TabloGallery (for gallery-based workflow)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function saveTabloSelection(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
            'photoId' => 'required|exists:media,id', // Gallery media ID
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Check if workflow is already completed
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if ($progress && $progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // Get steps_data for validation
        $stepsData = $progress ? ($progress->steps_data ?? []) : [];
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];

        // VALIDATION: Ensure tablo photo is in retouch photos (if retouch step was used)
        // Invariant: tablo_media_id ∈ retouch_media_ids (if retouch was used)
        if (!empty($retouchMediaIds) && !in_array($validated['photoId'], $retouchMediaIds)) {
            \Log::warning('[saveTabloSelection] Selected tablo photo not in retouch list', [
                'user_id' => $user->id,
                'media_id' => $validated['photoId'],
                'retouch_media_ids' => $retouchMediaIds,
            ]);

            return response()->json([
                'message' => 'A kiválasztott kép nincs a retusálandó képek között',
            ], 400);
        }

        // Get progress and claimed photos
        if (! $progress) {
            return response()->json([
                'message' => 'Nincs progress adat ehhez a galériához',
            ], 404);
        }

        $claimedMediaIds = $progress->steps_data['claimed_media_ids'] ?? [];

        if (empty($claimedMediaIds)) {
            return response()->json([
                'message' => 'Nincs kiválasztott kép',
            ], 400);
        }

        // Validate tablo_media_id is in claimed photos
        if (! in_array($validated['photoId'], $claimedMediaIds)) {
            return response()->json([
                'message' => 'A kiválasztott tablókép nincs a claim-elt képeid között',
            ], 400);
        }

        // Save tablo_media_id to progress and mark as completed
        // Gallery-based workflow: No need to move photos, just save media ID and complete
        $progress->update([
            'current_step' => 'completed',
            'steps_data' => array_merge($progress->steps_data ?? [], [
                'tablo_media_id' => $validated['photoId'],
                'tablo_photo_id' => $validated['photoId'], // Alias for compatibility
            ]),
        ]);

        \Log::info('[saveTabloSelection] Gallery-based workflow completed', [
            'user_id' => $user->id,
            'gallery_id' => $gallery->id,
            'tablo_media_id' => $validated['photoId'],
        ]);

        return response()->json(['message' => 'Tablókép választás mentve']);

        // OLD CODE (for photos.id workflow - not used in gallery mode):
        // Finalize workflow: create child album, copy photos, reserve in parent (first to complete wins!)
        // $parentSession = WorkSession::find($progress->work_session_id);
        // $parentAlbum = $parentSession->albums()->first();
        //
        // $result = $this->workflowService->finalizeTabloWorkflow(
        //     $user,
        //     $session,
        //     $parentAlbum,
        //     $claimedMediaIds // Should be photos.id!
        // );

        // Handle conflicts (photos already claimed by another user)
        if (! empty($result['conflicts'])) {
            \Log::warning('[TabloCompletion] Some photos already claimed by another user', [
                'user_id' => $user->id,
                'conflicts' => $result['conflicts'],
                'moved' => $result['moved'],
                'tablo_photo_id' => $validated['photoId'],
            ]);

            // Update progress to reflect actual moved photos with NEW photo IDs
            $stepsData = $progress->steps_data;

            // Map old photo IDs to new photo IDs using photoIdMapping
            $oldClaimedIds = $stepsData['claimed_photo_ids'] ?? [];
            $oldRetouchIds = $stepsData['retouch_photo_ids'] ?? [];
            $oldTabloPhotoId = $stepsData['tablo_photo_id'] ?? null;

            // Map to new IDs
            $newClaimedIds = [];
            foreach ($oldClaimedIds as $oldId) {
                if (isset($result['photoIdMapping'][$oldId])) {
                    $newClaimedIds[] = $result['photoIdMapping'][$oldId];
                }
            }

            $newRetouchIds = [];
            foreach ($oldRetouchIds as $oldId) {
                if (isset($result['photoIdMapping'][$oldId])) {
                    $newRetouchIds[] = $result['photoIdMapping'][$oldId];
                }
            }

            $newTabloPhotoId = null;
            if ($oldTabloPhotoId && isset($result['photoIdMapping'][$oldTabloPhotoId])) {
                $newTabloPhotoId = $result['photoIdMapping'][$oldTabloPhotoId];
            }

            // Update steps_data with new IDs
            $stepsData['claimed_photo_ids'] = $newClaimedIds;
            $stepsData['claimed_count'] = count($newClaimedIds);
            $stepsData['retouch_photo_ids'] = $newRetouchIds;
            $stepsData['retouch_count'] = count($newRetouchIds);
            $stepsData['tablo_photo_id'] = $newTabloPhotoId;

            $progress->update(['steps_data' => $stepsData]);

            \Log::info('[TabloCompletion] Progress photo IDs updated to child album IDs', [
                'user_id' => $user->id,
                'old_claimed_ids' => $oldClaimedIds,
                'new_claimed_ids' => $newClaimedIds,
                'old_retouch_ids' => $oldRetouchIds,
                'new_retouch_ids' => $newRetouchIds,
                'old_tablo_photo_id' => $oldTabloPhotoId,
                'new_tablo_photo_id' => $newTabloPhotoId,
            ]);
        }

        // Validate tablo_photo_id is in moved photos (not conflicted)
        if (! in_array($validated['photoId'], $result['moved'])) {
            return response()->json([
                'message' => 'A kiválasztott tablókép már nem elérhető (másnak ítélték)',
            ], 400);
        }

        // Remove conflicting photos from other users + log for email
        $this->workflowService->removeConflictingPhotosFromOtherUsers(
            $user,
            $parentSession,
            $result['moved']
        );

        // Mark workflow as completed
        $progress->update(['current_step' => 'completed']);

        // Get NEW tablo photo from child album (using photoIdMapping)
        $newTabloPhotoId = $result['photoIdMapping'][$validated['photoId']] ?? null;

        if (! $newTabloPhotoId) {
            \Log::error('[TabloCompletion] Tablo photo mapping not found', [
                'user_id' => $user->id,
                'old_photo_id' => $validated['photoId'],
                'photo_id_mapping' => $result['photoIdMapping'],
            ]);

            return response()->json([
                'message' => 'Belső hiba: tablókép másolása sikertelen',
            ], 500);
        }

        $newTabloPhoto = Photo::findOrFail($newTabloPhotoId);

        // Dispatch event to send completion email
        event(new TabloWorkflowCompleted(
            user: $user,
            workSession: $session,
            selectedPhoto: $newTabloPhoto
        ));

        return response()->json(['message' => 'Tablókép választás mentve']);
    }

    /**
     * Save cart comment
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function saveCartComment(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
            'comment' => 'required|string',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find or create progress
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'claiming',
                'steps_data' => [],
            ]
        );

        // Save comment
        $progress->update(['cart_comment' => $validated['comment']]);

        return response()->json(['message' => 'Megjegyzés mentve']);
    }

    /**
     * Get user progress for a gallery
     * NOTE: Route parameter should be {gallery} not {session} (kept for backward compatibility)
     */
    public function getProgress(Request $request, TabloGallery $gallery)
    {
        $user = $request->user();

        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Include gallery data for frontend
        return response()->json([
            'data' => $progress,
            'gallery' => [
                'id' => $gallery->id,
                'name' => $gallery->name,
                'photos_count' => $gallery->photos_count,
            ],
        ]);
    }

    /**
     * Get complete step data (UNIFIED ENDPOINT)
     * Replaces: /progress + /pricing-context + /photos
     * NOTE: Route parameter should be {gallery} not {session} (kept for backward compatibility)
     *
     * @param  Request  $request  HTTP request
     * @param  TabloGallery  $gallery  Tablo gallery
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStepData(Request $request, TabloGallery $gallery)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Nincs bejelentkezett felhasználó',
            ], 401);
        }

        // Get step from query param or determine from progress
        $step = $request->query('step');

        if (! $step) {
            // Auto-detect current step from progress
            $progress = TabloUserProgress::where('user_id', $user->id)
                ->where('tablo_gallery_id', $gallery->id)
                ->first();

            $step = $progress?->current_step ?? 'claiming';
        }

        // Validate step
        $validSteps = ['claiming', 'registration', 'retouch', 'tablo', 'completed'];
        if (! in_array($step, $validSteps)) {
            return response()->json([
                'message' => 'Érvénytelen lépés',
            ], 400);
        }

        // Get step data from service
        $stepData = $this->workflowService->getStepData($user, $gallery, $step);

        return response()->json($stepData);
    }

    /**
     * Move user to a specific tablo workflow step (for backward navigation)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     */
    public function moveToStep(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
            'targetStep' => 'required|in:claiming,registration,retouch,tablo',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find user progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if (! $progress) {
            return response()->json([
                'message' => 'Nincs progress adat ehhez a galériához',
            ], 404);
        }

        // Check if workflow is completed - can't go back from completed
        if ($progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet visszalépni',
            ], 403);
        }

        // Update current step
        $progress->update([
            'current_step' => $validated['targetStep'],
        ]);

        // Return FULL step data (same format as getStepData endpoint)
        // This eliminates the need for a separate loadStepData() call on frontend
        return response()->json(
            $this->workflowService->getStepData($user, $gallery, $validated['targetStep'])
        );
    }

    /**
     * Move to next step in tablo workflow (backend-driven navigation)
     * Automatically skips registration for customer users
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nextStep(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find user progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if (!$progress) {
            return response()->json([
                'message' => 'Nincs progress adat ehhez a galériához',
            ], 404);
        }

        // Cannot move forward from completed
        if ($progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett',
            ], 403);
        }

        // Auto-fix legacy data: customer user stuck on 'registration'
        if ($user->hasRole(User::ROLE_CUSTOMER) && $progress->current_step === 'registration') {
            \Log::warning('[TabloWorkflow] Auto-fixing customer user on registration step', [
                'user_id' => $user->id,
                'old_step' => 'registration',
                'new_step' => 'retouch',
            ]);

            $progress->update(['current_step' => 'retouch']);

            // Return FULL step data with auto_fixed flag
            $stepData = $this->workflowService->getStepData($user, $gallery, 'retouch');
            $stepData['auto_fixed'] = true;

            return response()->json($stepData);
        }

        // Determine next step
        $nextStep = $this->workflowService->determineNextStep($progress->current_step, $user);

        // Update progress
        $progress->update(['current_step' => $nextStep]);

        // Return FULL step data (same format as getStepData endpoint)
        // This eliminates the need for a separate loadStepData() call on frontend
        return response()->json(
            $this->workflowService->getStepData($user, $gallery, $nextStep)
        );
    }

    /**
     * Move to previous step in tablo workflow (backend-driven navigation)
     * Automatically skips registration for customer users (bidirectional)
     * NOTE: workSessionId parameter actually contains TabloGallery ID (for frontend compatibility)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function previousStep(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // MIGRATED: Gallery ID
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find user progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        if (!$progress) {
            return response()->json([
                'message' => 'Nincs progress adat ehhez a galériához',
            ], 404);
        }

        // Cannot go back from completed
        if ($progress->current_step === 'completed') {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet visszalépni',
            ], 403);
        }

        // Cannot go back from claiming (first step)
        if ($progress->current_step === 'claiming') {
            return response()->json([
                'message' => 'Ez az első lépés, nem lehet visszalépni',
            ], 400);
        }

        // Determine previous step
        $previousStep = $this->workflowService->determinePreviousStep($progress->current_step, $user);

        // Update progress
        $progress->update(['current_step' => $previousStep]);

        // Return FULL step data (same format as getStepData endpoint)
        // This eliminates the need for a separate loadStepData() call on frontend
        return response()->json(
            $this->workflowService->getStepData($user, $gallery, $previousStep)
        );
    }

    /**
     * Get workflow status for current user
     * Unified endpoint returning all workflow state data
     *
     * GET /api/tablo/workflow/status
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWorkflowStatus(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id', // Gallery ID
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Nincs bejelentkezett felhasználó',
            ], 401);
        }

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

        // Get max_retouch_photos from gallery
        $maxRetouchPhotos = $gallery->max_retouch_photos ?? 5;

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

        return response()->json([
            'current_step' => $currentStep,
            'workflow_status' => $workflowStatus,
            'max_retouch_photos' => $maxRetouchPhotos,
            'claimed_photos' => count($claimedMediaIds),
            'retouch_photo_ids' => $retouchMediaIds,
            'tablo_photo_id' => $tabloMediaId,
            'validation' => $validation,
            // Additional context
            'gallery' => [
                'id' => $gallery->id,
                'name' => $gallery->name,
                'photos_count' => $gallery->photos_count,
            ],
            'is_finalized' => $workflowStatus === TabloUserProgress::STATUS_FINALIZED,
        ]);
    }

    /**
     * Build validation info for frontend step navigation
     *
     * @param string $currentStep
     * @param array $claimedMediaIds
     * @param array $retouchMediaIds
     * @param int|null $tabloMediaId
     * @param int $maxRetouchPhotos
     * @param User $user
     * @return array
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

        // Check if customer (skips registration)
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
                // Customer users should not be on registration step
                if ($isCustomer) {
                    $validation['can_proceed'] = true;
                    $validation['warnings'][] = 'Regisztrált felhasználó - lépés kihagyható';
                } else {
                    // Guest user needs to register
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
                $validation['can_proceed'] = false; // Terminal state
                $validation['warnings'][] = 'A workflow már véglegesítve lett';
                break;
        }

        return $validation;
    }

    /**
     * Build cascade deletion message for frontend toast
     *
     * @param array{retouch: array<int>, tablo: bool} $cascadeDeleted
     * @return string
     */
    private function buildCascadeMessage(array $cascadeDeleted): string
    {
        $parts = [];

        if (!empty($cascadeDeleted['retouch'])) {
            $count = count($cascadeDeleted['retouch']);
            $parts[] = "{$count} kép eltávolítva a retusálás listádból";
        }

        if ($cascadeDeleted['tablo']) {
            $parts[] = 'A tablóképed frissítve lett';
        }

        return implode('. ', $parts);
    }

    /**
     * Save retouch step data via workflow endpoint
     * POST /api/tablo/workflow/retouch
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveWorkflowRetouch(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'photoIds' => 'present|array',
            'photoIds.*' => 'exists:media,id',
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoIds.present' => 'A képek listája kötelező.',
            'photoIds.array' => 'A képek listája érvénytelen formátumú.',
            'photoIds.*.exists' => 'A kiválasztott kép nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is finalized - return 403
        if ($progress && $progress->isFinalized()) {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        // Find or create progress record
        $progress = TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'retouch',
                'steps_data' => [],
                'workflow_status' => TabloUserProgress::STATUS_IN_PROGRESS,
            ]
        );

        // Update steps_data with retouch media IDs
        $stepsData = $progress->steps_data ?? [];

        // VALIDATION: Ensure all retouch photos are claimed photos
        $claimedMediaIds = $stepsData['claimed_media_ids'] ?? [];
        $requestedRetouchIds = $validated['photoIds'];

        // Filter: only keep retouch IDs that are in claimed set
        $validRetouchIds = array_values(array_intersect($requestedRetouchIds, $claimedMediaIds));

        // Log filtered photos
        if (count($validRetouchIds) < count($requestedRetouchIds)) {
            $filtered = array_diff($requestedRetouchIds, $validRetouchIds);
            \Log::warning('[saveWorkflowRetouch] Filtered non-claimed photos from retouch', [
                'user_id' => $user->id,
                'filtered_ids' => $filtered,
            ]);
        }

        // Track cascade deletions
        $cascadeDeleted = ['tablo' => false];

        // Cascade delete tablo_media_id if not in new retouch set
        if (isset($stepsData['tablo_media_id'])) {
            $tabloMediaId = $stepsData['tablo_media_id'];
            if (!in_array($tabloMediaId, $validRetouchIds)) {
                $cascadeDeleted['tablo'] = true;
                unset($stepsData['tablo_media_id']);
            }
        }

        $stepsData['retouch_media_ids'] = $validRetouchIds;
        $stepsData['retouch_count'] = count($validRetouchIds);

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        // Build response
        $response = [
            'message' => 'Retusálási választás mentve',
            'retouch_photo_ids' => $validRetouchIds,
        ];

        if ($cascadeDeleted['tablo']) {
            $response['cascade_deleted'] = $cascadeDeleted;
            $response['cascade_message'] = 'A tablóképed frissítve lett';
        }

        return response()->json($response);
    }

    /**
     * Save tablo photo step data via workflow endpoint
     * POST /api/tablo/workflow/tablo-photo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveWorkflowTabloPhoto(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'photoId' => 'required|exists:media,id',
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
            'photoId.required' => 'A tablókép azonosító kötelező.',
            'photoId.exists' => 'A kiválasztott kép nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is finalized - return 403
        if ($progress && $progress->isFinalized()) {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett, nem lehet módosítani',
            ], 403);
        }

        if (!$progress) {
            return response()->json([
                'message' => 'Nincs progress adat ehhez a galériához',
            ], 404);
        }

        // Get steps data for validation
        $stepsData = $progress->steps_data ?? [];
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];

        // VALIDATION: Ensure tablo photo is in retouch photos
        if (!empty($retouchMediaIds) && !in_array($validated['photoId'], $retouchMediaIds)) {
            return response()->json([
                'message' => 'A kiválasztott kép nincs a retusálandó képek között',
            ], 400);
        }

        // Save tablo media ID
        $stepsData['tablo_media_id'] = $validated['photoId'];

        $progress->update([
            'steps_data' => $stepsData,
        ]);

        return response()->json([
            'message' => 'Tablókép választás mentve',
            'tablo_photo_id' => $validated['photoId'],
        ]);
    }

    /**
     * Finalize workflow - mark as completed (no more modifications allowed)
     * POST /api/tablo/workflow/finalize
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function finalizeWorkflow(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        // Find existing progress
        $progress = TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        // Check if workflow is already finalized - return 403
        if ($progress && $progress->isFinalized()) {
            return response()->json([
                'message' => 'A megrendelés már véglegesítve lett',
            ], 403);
        }

        if (!$progress) {
            return response()->json([
                'message' => 'Nincs progress adat ehhez a galériához',
            ], 404);
        }

        // Validate workflow is ready to finalize
        $stepsData = $progress->steps_data ?? [];
        $claimedMediaIds = $stepsData['claimed_media_ids'] ?? [];
        $retouchMediaIds = $stepsData['retouch_media_ids'] ?? [];
        $tabloMediaId = $stepsData['tablo_media_id'] ?? null;

        // Validation: need claimed photos
        if (empty($claimedMediaIds)) {
            return response()->json([
                'message' => 'Nincs kiválasztott kép',
            ], 400);
        }

        // Validation: need tablo photo
        if (!$tabloMediaId) {
            return response()->json([
                'message' => 'Nincs tablókép kiválasztva',
            ], 400);
        }

        // Validation: tablo photo must be in retouch photos (if retouch step used)
        if (!empty($retouchMediaIds) && !in_array($tabloMediaId, $retouchMediaIds)) {
            return response()->json([
                'message' => 'A tablókép nincs a retusálandó képek között',
            ], 400);
        }

        // Finalize workflow
        $progress->update([
            'current_step' => 'completed',
            'workflow_status' => TabloUserProgress::STATUS_FINALIZED,
            'finalized_at' => now(),
        ]);

        \Log::info('[finalizeWorkflow] Workflow finalized', [
            'user_id' => $user->id,
            'gallery_id' => $gallery->id,
            'claimed_count' => count($claimedMediaIds),
            'retouch_count' => count($retouchMediaIds),
            'tablo_media_id' => $tabloMediaId,
        ]);

        return response()->json([
            'message' => 'Megrendelés véglegesítve',
            'finalized_at' => now()->toIso8601String(),
        ]);
    }
}
