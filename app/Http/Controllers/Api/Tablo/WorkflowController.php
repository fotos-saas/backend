<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Actions\Tablo\FinalizeWorkflowAction;
use App\Actions\Tablo\GetWorkflowStatusAction;
use App\Actions\Tablo\NavigateWorkflowAction;
use App\Actions\Tablo\RequestModificationAction;
use App\Actions\Tablo\SaveClaimingSelectionAction;
use App\Actions\Tablo\SaveRetouchSelectionAction;
use App\Actions\Tablo\SaveTabloPhotoAction;
use App\Http\Controllers\Controller;
use App\Models\TabloGallery;
use App\Services\TabloWorkflowService;
use Illuminate\Http\Request;

/**
 * Tablo Workflow Controller
 *
 * Handles the tablo photo selection workflow using Action classes.
 * Each action is delegated to a dedicated Action class for better maintainability.
 */
class WorkflowController extends Controller
{
    public function __construct(
        private TabloWorkflowService $workflowService
    ) {}

    // ==========================================
    // SELECTION ENDPOINTS
    // ==========================================

    /**
     * Save claiming selection (photos user claimed as their own)
     */
    public function saveClaiming(Request $request, SaveClaimingSelectionAction $action)
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

        $result = $action->execute($user, $gallery, $validated['photoIds']);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }

    /**
     * Save retouch selection
     */
    public function saveRetouch(Request $request, SaveRetouchSelectionAction $action)
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

        $result = $action->execute($user, $gallery, $validated['photoIds']);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }

    /**
     * Save tablo photo selection
     */
    public function saveTabloPhoto(Request $request, SaveTabloPhotoAction $action)
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

        $result = $action->execute($user, $gallery, $validated['photoId']);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }

    /**
     * Clear tablo photo selection
     */
    public function clearTabloPhoto(Request $request, SaveTabloPhotoAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $result = $action->clear($user, $gallery);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }

    // ==========================================
    // NAVIGATION ENDPOINTS
    // ==========================================

    /**
     * Move to next step
     */
    public function nextStep(Request $request, NavigateWorkflowAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $result = $action->nextStep($user, $gallery);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result['data']);
    }

    /**
     * Move to previous step
     */
    public function previousStep(Request $request, NavigateWorkflowAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $result = $action->previousStep($user, $gallery);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result['data']);
    }

    /**
     * Move to specific step
     */
    public function moveToStep(Request $request, NavigateWorkflowAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'targetStep' => 'required|in:claiming,registration,retouch,tablo',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $result = $action->moveToStep($user, $gallery, $validated['targetStep']);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result['data']);
    }

    // ==========================================
    // STATUS ENDPOINTS
    // ==========================================

    /**
     * Get workflow status
     */
    public function getStatus(Request $request, GetWorkflowStatusAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Nincs bejelentkezett felhasználó'], 401);
        }

        return response()->json($action->execute($user, $gallery));
    }

    /**
     * Get step data
     */
    public function getStepData(Request $request, TabloGallery $gallery)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Nincs bejelentkezett felhasználó'], 401);
        }

        $step = $request->query('step');

        if (!$step) {
            $progress = \App\Models\TabloUserProgress::where('user_id', $user->id)
                ->where('tablo_gallery_id', $gallery->id)
                ->first();

            $step = $progress?->current_step ?? 'claiming';
        }

        $validSteps = ['claiming', 'registration', 'retouch', 'tablo', 'completed'];
        if (!in_array($step, $validSteps)) {
            return response()->json(['message' => 'Érvénytelen lépés'], 400);
        }

        return response()->json($this->workflowService->getStepData($user, $gallery, $step));
    }

    /**
     * Get progress
     */
    public function getProgress(Request $request, TabloGallery $gallery)
    {
        $user = $request->user();

        $progress = \App\Models\TabloUserProgress::where('user_id', $user->id)
            ->where('tablo_gallery_id', $gallery->id)
            ->first();

        return response()->json([
            'data' => $progress,
            'gallery' => [
                'id' => $gallery->id,
                'name' => $gallery->name,
                'photos_count' => $gallery->photos_count,
            ],
        ]);
    }

    // ==========================================
    // FINALIZATION
    // ==========================================

    /**
     * Finalize workflow
     */
    public function finalize(Request $request, FinalizeWorkflowAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $result = $action->execute($user, $gallery);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }

    /**
     * Request modification (un-finalize workflow)
     */
    public function requestModification(Request $request, RequestModificationAction $action)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
        ], [
            'workSessionId.required' => 'A galéria azonosító kötelező.',
            'workSessionId.exists' => 'A megadott galéria nem található.',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $result = $action->execute($user, $gallery);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json($result);
    }

    // ==========================================
    // MISC ENDPOINTS
    // ==========================================

    /**
     * Save cart comment
     */
    public function saveCartComment(Request $request)
    {
        $validated = $request->validate([
            'workSessionId' => 'required|exists:tablo_galleries,id',
            'comment' => 'required|string',
        ]);

        $gallery = TabloGallery::findOrFail($validated['workSessionId']);
        $user = $request->user();

        $progress = \App\Models\TabloUserProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'tablo_gallery_id' => $gallery->id,
            ],
            [
                'current_step' => 'claiming',
                'steps_data' => [],
            ]
        );

        $progress->update(['cart_comment' => $validated['comment']]);

        return response()->json(['message' => 'Megjegyzés mentve']);
    }
}
