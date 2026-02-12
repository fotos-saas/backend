<?php

namespace App\Traits;

use App\Models\Partner;
use App\Models\PartnerTeamMember;
use App\Models\TabloPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Csapattagsag kezeles (szabaduszok, grafikusok, nyomdaszok, ugyintezok).
 *
 * A User model-hez tartozo team membership logika.
 */
trait HasTeamMembership
{
    /**
     * Partnerek ahol csapattag (szabaduszomModell)
     */
    public function partnerMemberships(): HasMany
    {
        return $this->hasMany(PartnerTeamMember::class);
    }

    /**
     * Aktiv partner tagsagok
     */
    public function activePartnerMemberships(): HasMany
    {
        return $this->partnerMemberships()->where('is_active', true);
    }

    /**
     * Partnerek (BelongsToMany)
     */
    public function memberOfPartners(): BelongsToMany
    {
        return $this->belongsToMany(TabloPartner::class, 'partner_team_members', 'user_id', 'partner_id')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    /**
     * Adott szerepkorrel dolgozik-e a partnernel?
     */
    public function hasRoleAtPartner(int $partnerId, string $role): bool
    {
        return $this->partnerMemberships()
            ->where('partner_id', $partnerId)
            ->where('role', $role)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Aktiv csapattag-e barmelyik partnernel?
     */
    public function isTeamMember(): bool
    {
        return $this->activePartnerMemberships()->exists();
    }

    /**
     * Grafikus-e?
     */
    public function isDesigner(): bool
    {
        return $this->hasRole(self::ROLE_DESIGNER) || $this->activePartnerMemberships()->where('role', self::ROLE_DESIGNER)->exists();
    }

    /**
     * Nyomdasz-e?
     */
    public function isPrinter(): bool
    {
        return $this->hasRole(self::ROLE_PRINTER) || $this->activePartnerMemberships()->where('role', self::ROLE_PRINTER)->exists();
    }

    /**
     * Ugyintezo-e?
     */
    public function isAssistant(): bool
    {
        return $this->hasRole(self::ROLE_ASSISTANT) || $this->activePartnerMemberships()->where('role', self::ROLE_ASSISTANT)->exists();
    }

    /**
     * Get the effective partner for this user (own or via team membership).
     * Csapattagok eseten a tablo_partner_id alapjan keresi meg a Partner-t (a tulajdonos useren keresztul).
     */
    public function getEffectivePartner(): ?Partner
    {
        // Ha sajat partner van (tulajdonos)
        if ($this->partner) {
            return $this->partner;
        }

        // Ha csapattag, keressuk meg a Partner-t
        if ($this->tablo_partner_id) {
            $tabloPartner = TabloPartner::find($this->tablo_partner_id);
            if ($tabloPartner) {
                // 1. Direct FK (partner_id -> Partner)
                if ($tabloPartner->subscriptionPartner) {
                    return $tabloPartner->subscriptionPartner;
                }
                // 2. Fallback: email match (legacy)
                if ($tabloPartner->email) {
                    $ownerUser = User::where('email', $tabloPartner->email)->first();
                    return $ownerUser?->partner;
                }
            }
        }

        return null;
    }
}
