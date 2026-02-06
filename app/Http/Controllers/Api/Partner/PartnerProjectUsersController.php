<?php

namespace App\Http\Controllers\Api\Partner;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Requests\Api\Partner\UpdateGuestSessionRequest;
use App\Models\TabloGuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerProjectUsersController extends Controller
{
    use PartnerAuthTrait;

    /**
     * Guest session-ök listázása (paginált, kereshető, szűrhető)
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $perPage = min((int) $request->input('per_page', 15), 50);
        $search = $request->input('search');
        $filter = $request->input('filter');

        $query = TabloGuestSession::where('tablo_project_id', $project->id)
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at');

        if ($search) {
            $pattern = QueryHelper::safeLikePattern($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('guest_name', 'ILIKE', $pattern)
                    ->orWhere('guest_email', 'ILIKE', $pattern);
            });
        }

        if ($filter) {
            match ($filter) {
                'active' => $query->active(),
                'banned' => $query->banned(),
                'verified' => $query->verified(),
                'pending' => $query->pending(),
                default => null,
            };
        }

        $sessions = $query->paginate($perPage);

        $sessions->getCollection()->transform(function (TabloGuestSession $session) {
            return [
                'id' => $session->id,
                'guestName' => $session->guest_name,
                'guestEmail' => $session->guest_email,
                'ipAddress' => $session->ip_address,
                'isBanned' => $session->is_banned,
                'isExtra' => $session->is_extra,
                'isCoordinator' => $session->is_coordinator,
                'verificationStatus' => $session->verification_status,
                'points' => $session->points ?? 0,
                'rankLevel' => $session->rank_level ?? 1,
                'rankName' => $session->rank_name,
                'registrationType' => $session->registration_type,
                'registrationTypeLabel' => $session->registration_type
                    ? \App\Enums\QrCodeType::tryFrom($session->registration_type)?->label()
                    : null,
                'lastActivityAt' => $session->last_activity_at?->toIso8601String(),
                'createdAt' => $session->created_at->toIso8601String(),
            ];
        });

        return response()->json($sessions);
    }

    /**
     * Guest session módosítása (név/email)
     */
    public function update(UpdateGuestSessionRequest $request, int $projectId, int $sessionId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $session = TabloGuestSession::where('id', $sessionId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        $session->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Felhasználó módosítva.',
            'data' => [
                'id' => $session->id,
                'guestName' => $session->guest_name,
                'guestEmail' => $session->guest_email,
            ],
        ]);
    }

    /**
     * Guest session törlése
     */
    public function destroy(int $projectId, int $sessionId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $session = TabloGuestSession::where('id', $sessionId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        $session->delete();

        $project->updateGuestsCount();

        return response()->json([
            'success' => true,
            'message' => 'Felhasználó törölve.',
        ]);
    }

    /**
     * Ban/unban toggle
     */
    public function toggleBan(int $projectId, int $sessionId): JsonResponse
    {
        $project = $this->getProjectForPartner($projectId);

        $session = TabloGuestSession::where('id', $sessionId)
            ->where('tablo_project_id', $project->id)
            ->firstOrFail();

        if ($session->is_banned) {
            $session->unban();
            $message = 'Felhasználó feloldva.';
        } else {
            $session->ban();
            $message = 'Felhasználó tiltva.';
        }

        $project->updateGuestsCount();

        return response()->json([
            'success' => true,
            'message' => $message,
            'isBanned' => $session->fresh()->is_banned,
        ]);
    }
}
