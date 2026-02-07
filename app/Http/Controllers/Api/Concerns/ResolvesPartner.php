<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Partner;
use App\Models\TabloPartner;
use App\Models\User;

/**
 * Partner feloldás trait - a bejelentkezett user Partner modelljének megkeresése.
 *
 * Két esetet kezel:
 * 1. A user saját Partner rekordja (user_id alapján)
 * 2. Csapattag fallback: TabloPartner → tulajdonos email → tulajdonos Partner
 */
trait ResolvesPartner
{
    /**
     * Partner feloldása a bejelentkezett user alapján.
     */
    protected function resolvePartner(int $userId): ?Partner
    {
        $partner = Partner::where('user_id', $userId)->first();

        if ($partner) {
            return $partner;
        }

        return $this->resolvePartnerViaTeam($userId);
    }

    /**
     * Partner feloldása aktív addonokkal együtt.
     */
    protected function resolvePartnerWithAddons(int $userId): ?Partner
    {
        $partner = Partner::with(['addons' => fn ($q) => $q->where('status', 'active')])
            ->where('user_id', $userId)
            ->first();

        if ($partner) {
            return $partner;
        }

        return $this->resolvePartnerViaTeamWithAddons($userId);
    }

    /**
     * Csapattag → tulajdonos Partner feloldás.
     */
    private function resolvePartnerViaTeam(int $userId): ?Partner
    {
        $ownerUserId = $this->resolveOwnerUserId($userId);

        if (! $ownerUserId) {
            return null;
        }

        return Partner::where('user_id', $ownerUserId)->first();
    }

    /**
     * Csapattag → tulajdonos Partner feloldás (addonokkal).
     */
    private function resolvePartnerViaTeamWithAddons(int $userId): ?Partner
    {
        $ownerUserId = $this->resolveOwnerUserId($userId);

        if (! $ownerUserId) {
            return null;
        }

        return Partner::with(['addons' => fn ($q) => $q->where('status', 'active')])
            ->where('user_id', $ownerUserId)
            ->first();
    }

    /**
     * Tulajdonos user ID feloldása csapattag alapján.
     *
     * Priority: TabloPartner.partner_id FK → Partner.user_id
     * Fallback: TabloPartner.email → User.email → Partner.user_id
     */
    private function resolveOwnerUserId(int $userId): ?int
    {
        $user = User::find($userId);

        if (! $user || ! $user->tablo_partner_id) {
            return null;
        }

        $tabloPartner = TabloPartner::find($user->tablo_partner_id);

        if (! $tabloPartner) {
            return null;
        }

        // 1. Direct FK: partner_id → Partner → user_id
        if ($tabloPartner->subscriptionPartner) {
            return $tabloPartner->subscriptionPartner->user_id;
        }

        // 2. Fallback: email match (legacy)
        if (! $tabloPartner->email) {
            return null;
        }

        $ownerUser = User::where('email', $tabloPartner->email)->first();

        return $ownerUser?->id;
    }
}
