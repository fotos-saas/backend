<?php

namespace App\Services;

use App\Mail\TeamInvitationMail;
use App\Models\PartnerInvitation;
use App\Models\PartnerTeamMember;
use App\Models\TabloPartner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Partner Invitation Service
 *
 * Meghívások kezelése (csapattagok és partnerek).
 */
class PartnerInvitationService
{
    /**
     * Új meghívó létrehozása
     */
    public function createInvitation(
        TabloPartner $partner,
        string $email,
        string $role,
        string $type = PartnerInvitation::TYPE_TEAM_MEMBER
    ): PartnerInvitation {
        // Ellenőrzés: van-e már aktív meghívó erre az emailre
        $existingInvitation = $partner->invitations()
            ->where('email', $email)
            ->where('role', $role)
            ->pending()
            ->first();

        if ($existingInvitation) {
            // Ha már van, újraküldjük
            $this->sendInvitationEmail($existingInvitation);
            return $existingInvitation;
        }

        // Új meghívó létrehozása
        $invitation = PartnerInvitation::create([
            'partner_id' => $partner->id,
            'code' => PartnerInvitation::generateCode(),
            'email' => strtolower(trim($email)),
            'type' => $type,
            'role' => $role,
            'status' => PartnerInvitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7), // 7 nap érvényesség
        ]);

        // Email küldése
        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    /**
     * Meghívó kód validálása
     */
    public function validateCode(string $code): ?PartnerInvitation
    {
        $invitation = PartnerInvitation::findValidCode($code);

        if (! $invitation) {
            return null;
        }

        // Lejárt meghívók státuszának frissítése
        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->markAsExpired();
            return null;
        }

        return $invitation;
    }

    /**
     * Meghívó elfogadása (regisztráció után)
     */
    public function acceptInvitation(PartnerInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            // Meghívó elfogadása
            $invitation->accept($user);

            // Csapattag meghívó esetén: hozzáadás a partner csapatához
            if ($invitation->isTeamMemberInvitation()) {
                $this->addUserToTeam($invitation->partner, $user, $invitation->role);

                // Spatie role hozzáadása a csapattag szerepköre alapján
                // designer, marketer, printer, assistant
                $spatieRole = $invitation->role;
                if (! $user->hasRole($spatieRole)) {
                    $user->assignRole($spatieRole);
                }
            }
        });
    }

    /**
     * Meghívó visszavonása
     */
    public function revokeInvitation(PartnerInvitation $invitation): void
    {
        if ($invitation->status !== PartnerInvitation::STATUS_PENDING) {
            throw new \InvalidArgumentException('Csak függőben lévő meghívó vonható vissza.');
        }

        $invitation->revoke();
    }

    /**
     * Meghívó email küldése
     */
    public function sendInvitationEmail(PartnerInvitation $invitation): void
    {
        Mail::to($invitation->email)->send(new TeamInvitationMail($invitation));
    }

    /**
     * User hozzáadása a partner csapatához
     */
    public function addUserToTeam(TabloPartner $partner, User $user, string $role): PartnerTeamMember
    {
        // Ha már tag ezzel a szerepkörrel, aktiváljuk
        $existing = PartnerTeamMember::where('partner_id', $partner->id)
            ->where('user_id', $user->id)
            ->where('role', $role)
            ->first();

        if ($existing) {
            $existing->activate();
            return $existing;
        }

        // Új tagság létrehozása
        return PartnerTeamMember::create([
            'partner_id' => $partner->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * User eltávolítása a partner csapatából
     */
    public function removeUserFromTeam(TabloPartner $partner, User $user, ?string $role = null): void
    {
        $query = PartnerTeamMember::where('partner_id', $partner->id)
            ->where('user_id', $user->id);

        if ($role) {
            $query->where('role', $role);
        }

        // Soft delete: deaktiválás
        $query->update(['is_active' => false]);
    }

    /**
     * Lejárt meghívók megjelölése
     */
    public function expireOldInvitations(): int
    {
        return PartnerInvitation::where('status', PartnerInvitation::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => PartnerInvitation::STATUS_EXPIRED]);
    }

    /**
     * Partner csapattagjainak listázása
     */
    public function getTeamMembers(TabloPartner $partner): array
    {
        $members = $partner->teamMembers()
            ->with('user')
            ->where('is_active', true)
            ->get();

        return $members->map(function (PartnerTeamMember $member) {
            return [
                'id' => $member->id,
                'userId' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role,
                'roleName' => $member->role_name,
                'joinedAt' => $member->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Függőben lévő meghívók listázása
     */
    public function getPendingInvitations(TabloPartner $partner): array
    {
        $invitations = $partner->invitations()
            ->pending()
            ->teamMember()
            ->orderBy('created_at', 'desc')
            ->get();

        return $invitations->map(function (PartnerInvitation $invitation) {
            return [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'roleName' => $invitation->role_name,
                'code' => $invitation->code,
                'inviteUrl' => $invitation->getRegistrationUrl(),
                'createdAt' => $invitation->created_at->toIso8601String(),
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
            ];
        })->toArray();
    }
}
