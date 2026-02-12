<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\UpdateTeamMemberRoleRequest;
use App\Models\PartnerInvitation;
use App\Services\PartnerInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Team Controller
 *
 * Partner csapattagok kezelése.
 */
class TeamController extends Controller
{
    public function __construct(
        private readonly PartnerInvitationService $invitationService
    ) {}

    /**
     * Csapat összefoglaló (tagok + meghívók)
     *
     * GET /api/partner/team
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = $user->tabloPartner;

        if (! $partner) {
            return response()->json([
                'message' => 'Nincs partner fiók',
            ], 403);
        }

        $members = $this->invitationService->getTeamMembers($partner);
        $invitations = $this->invitationService->getPendingInvitations($partner);

        return response()->json([
            'members' => $members,
            'pendingInvitations' => $invitations,
            'roles' => PartnerInvitation::getTeamMemberRoles(),
        ]);
    }

    /**
     * Csapattag eltávolítása
     *
     * DELETE /api/partner/team/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $partner = $user->tabloPartner;

        if (! $partner) {
            return response()->json([
                'message' => 'Nincs partner fiók',
            ], 403);
        }

        $member = $partner->teamMembers()->find($id);

        if (! $member) {
            return response()->json([
                'message' => 'Csapattag nem található.',
            ], 404);
        }

        // Eltávolítás (deaktiválás)
        $member->deactivate();

        return response()->json([
            'message' => 'Csapattag eltávolítva.',
        ]);
    }

    /**
     * Csapattag szerepkörének módosítása
     *
     * PUT /api/partner/team/{id}
     */
    public function update(UpdateTeamMemberRoleRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $partner = $user->tabloPartner;

        if (! $partner) {
            return response()->json([
                'message' => 'Nincs partner fiók',
            ], 403);
        }

        $validated = $request->validated();

        $member = $partner->teamMembers()->find($id);

        if (! $member) {
            return response()->json([
                'message' => 'Csapattag nem található.',
            ], 404);
        }

        // Ha már van ilyen szerepkörrel ugyanez a user
        $existing = $partner->teamMembers()
            ->where('user_id', $member->user_id)
            ->where('role', $validated['role'])
            ->where('id', '!=', $id)
            ->where('is_active', true)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'A felhasználónak már van ilyen szerepköre.',
            ], 422);
        }

        $member->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Szerepkör módosítva.',
            'data' => [
                'id' => $member->id,
                'role' => $member->role,
                'roleName' => $member->role_name,
            ],
        ]);
    }
}
