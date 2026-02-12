<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Partner\CreateInvitationRequest;
use App\Models\PartnerInvitation;
use App\Services\PartnerInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Invitation Controller
 *
 * Partner meghívások kezelése (csapattagok).
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly PartnerInvitationService $invitationService
    ) {}

    /**
     * Meghívók listázása (függőben lévők)
     *
     * GET /api/partner/invitations
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

        $invitations = $this->invitationService->getPendingInvitations($partner);

        return response()->json([
            'data' => $invitations,
        ]);
    }

    /**
     * Új meghívó küldése
     *
     * POST /api/partner/invitations
     */
    public function store(CreateInvitationRequest $request): JsonResponse
    {
        $user = $request->user();
        $partner = $user->tabloPartner;

        if (! $partner) {
            return response()->json([
                'message' => 'Nincs partner fiók',
            ], 403);
        }

        $validated = $request->validated();

        // Ellenőrzés: a user nincs-e már a csapatban ezzel a role-lal
        $existingMember = $partner->teamMembers()
            ->whereHas('user', fn ($q) => $q->where('email', $validated['email']))
            ->where('role', $validated['role'])
            ->where('is_active', true)
            ->exists();

        if ($existingMember) {
            return response()->json([
                'message' => 'Ez a felhasználó már a csapat tagja ezzel a szerepkörrel.',
            ], 422);
        }

        $invitation = $this->invitationService->createInvitation(
            $partner,
            $validated['email'],
            $validated['role']
        );

        return response()->json([
            'message' => 'Meghívó sikeresen elküldve.',
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'roleName' => $invitation->role_name,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Meghívó visszavonása
     *
     * DELETE /api/partner/invitations/{id}
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

        $invitation = $partner->invitations()->find($id);

        if (! $invitation) {
            return response()->json([
                'message' => 'Meghívó nem található.',
            ], 404);
        }

        if ($invitation->status !== PartnerInvitation::STATUS_PENDING) {
            return response()->json([
                'message' => 'Csak függőben lévő meghívó vonható vissza.',
            ], 422);
        }

        $this->invitationService->revokeInvitation($invitation);

        return response()->json([
            'message' => 'Meghívó visszavonva.',
        ]);
    }

    /**
     * Meghívó újraküldése
     *
     * POST /api/partner/invitations/{id}/resend
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $partner = $user->tabloPartner;

        if (! $partner) {
            return response()->json([
                'message' => 'Nincs partner fiók',
            ], 403);
        }

        $invitation = $partner->invitations()->find($id);

        if (! $invitation) {
            return response()->json([
                'message' => 'Meghívó nem található.',
            ], 404);
        }

        if ($invitation->status !== PartnerInvitation::STATUS_PENDING) {
            return response()->json([
                'message' => 'Csak függőben lévő meghívó küldhető újra.',
            ], 422);
        }

        $this->invitationService->sendInvitationEmail($invitation);

        return response()->json([
            'message' => 'Meghívó újraküldve.',
        ]);
    }
}
