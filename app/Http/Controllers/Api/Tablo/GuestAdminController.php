<?php

namespace App\Http\Controllers\Api\Tablo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tablo\Guest\ResolveConflictRequest;
use App\Http\Requests\Api\Tablo\Guest\SetClassSizeRequest;
use App\Models\TabloGuestSession;
use App\Models\TabloProject;
use App\Services\Tablo\GuestSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guest Admin Controller
 *
 * Vendég admin műveletek (kapcsolattartó számára).
 * Token-ból azonosítja a projektet.
 */
class GuestAdminController extends Controller
{
    public function __construct(
        protected GuestSessionService $guestSessionService
    ) {}

    /**
     * Get guests list (contact only).
     * GET /api/tablo-frontend/admin/guests
     */
    public function getGuests(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $includeBanned = $request->boolean('include_banned', true);
        $guests = $this->guestSessionService->getGuestsByProject($project, $includeBanned);

        // Aktuális guest ID lekérése X-Guest-Session headerből
        $currentGuestId = null;
        $guestSessionToken = $request->header('X-Guest-Session');
        if ($guestSessionToken) {
            $currentGuest = TabloGuestSession::where('session_token', $guestSessionToken)
                ->where('tablo_project_id', $project->id)
                ->first();
            $currentGuestId = $currentGuest?->id;
        }

        return response()->json([
            'success' => true,
            'data' => $guests->map(fn ($guest) => [
                'id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'guest_email' => $guest->guest_email,
                'is_banned' => $guest->is_banned,
                'is_extra' => $guest->is_extra,
                'last_activity_at' => $guest->last_activity_at?->toIso8601String(),
                'created_at' => $guest->created_at->toIso8601String(),
                'votes_count' => $guest->votes_count,
            ]),
            'statistics' => $this->guestSessionService->getGuestStatistics($project),
            'current_guest_id' => $currentGuestId,
        ]);
    }

    /**
     * Ban guest (contact only).
     * POST /api/tablo-frontend/admin/guests/{id}/ban
     */
    public function ban(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Vendég nem található',
            ], 404);
        }

        $this->guestSessionService->ban($session);

        return response()->json([
            'success' => true,
            'message' => 'Vendég sikeresen tiltva!',
        ]);
    }

    /**
     * Unban guest (contact only).
     * POST /api/tablo-frontend/admin/guests/{id}/unban
     */
    public function unban(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Vendég nem található',
            ], 404);
        }

        $this->guestSessionService->unban($session);

        return response()->json([
            'success' => true,
            'message' => 'Vendég tiltása feloldva!',
        ]);
    }

    /**
     * Toggle extra status (contact only).
     * PUT /api/tablo-frontend/admin/guests/{id}/extra
     */
    public function toggleExtra(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Vendég nem található',
            ], 404);
        }

        $session->toggleExtra();

        return response()->json([
            'success' => true,
            'message' => $session->is_extra
                ? 'Vendég extra tagként jelölve!'
                : 'Extra jelölés eltávolítva!',
            'is_extra' => $session->is_extra,
        ]);
    }

    /**
     * Set expected class size (contact only).
     * PUT /api/tablo-frontend/admin/class-size
     */
    public function setClassSize(SetClassSizeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $project->update([
            'expected_class_size' => $validated['expected_class_size'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Osztálylétszám sikeresen beállítva!',
            'data' => [
                'expected_class_size' => $project->expected_class_size,
            ],
        ]);
    }

    /**
     * Get pending sessions (admin only).
     * GET /api/tablo-frontend/admin/pending-sessions
     *
     * Pending státuszú session-ök listája ütközéskezeléshez.
     */
    public function getPendingSessions(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $project = TabloProject::find($token->tablo_project_id);

        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Projekt nem található',
            ], 404);
        }

        $pendingSessions = $this->guestSessionService->getPendingSessions($project);

        return response()->json([
            'success' => true,
            'data' => $pendingSessions->map(fn ($session) => [
                'id' => $session->id,
                'guest_name' => $session->guest_name,
                'guest_email' => $session->guest_email,
                'person' => $session->person ? [
                    'id' => $session->person->id,
                    'name' => $session->person->name,
                    'type' => $session->person->type,
                    'type_label' => $session->person->type_label,
                ] : null,
                'created_at' => $session->created_at->toIso8601String(),
                // Megmutatjuk ki az eredeti "tulajdonos"
                'existing_owner' => $session->person?->guestSession ? [
                    'id' => $session->person->guestSession->id,
                    'guest_name' => $session->person->guestSession->guest_name,
                ] : null,
            ]),
            'count' => $pendingSessions->count(),
        ]);
    }

    /**
     * Resolve conflict (admin only).
     * POST /api/tablo-frontend/admin/guests/{id}/resolve-conflict
     *
     * Pending státuszú session jóváhagyása vagy elutasítása.
     */
    public function resolveConflict(ResolveConflictRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $token = $request->user()->currentAccessToken();
        $projectId = $token->tablo_project_id;

        $session = TabloGuestSession::where('id', $id)
            ->where('tablo_project_id', $projectId)
            ->pending()
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Pending session nem található',
            ], 404);
        }

        $resolvedSession = $this->guestSessionService->resolveConflict($session, $validated['approve']);

        return response()->json([
            'success' => true,
            'message' => $validated['approve']
                ? 'Kérés jóváhagyva!'
                : 'Kérés elutasítva!',
            'data' => [
                'id' => $resolvedSession->id,
                'verification_status' => $resolvedSession->verification_status,
                'guest_name' => $resolvedSession->guest_name,
            ],
        ]);
    }
}
