<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partner Invitation Model
 *
 * Csapattag és partner meghívások kezelése.
 *
 * @property int $id
 * @property int $partner_id
 * @property string $code INVITE-XXXXXX formátumú kód
 * @property string $email Meghívott email címe
 * @property string $type team_member | partner
 * @property string $role designer | marketer | printer | assistant | photo_studio | print_shop
 * @property string $status pending | accepted | expired | revoked
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $accepted_at
 * @property int|null $accepted_by_user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PartnerInvitation extends Model
{
    use HasFactory;

    // Meghívó típusok
    public const TYPE_TEAM_MEMBER = 'team_member';
    public const TYPE_PARTNER = 'partner';

    // Státuszok
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    // Csapattag szerepkörök
    public const ROLE_DESIGNER = 'designer';
    public const ROLE_MARKETER = 'marketer';
    public const ROLE_PRINTER = 'printer';
    public const ROLE_ASSISTANT = 'assistant';

    // Partner szerepkörök (meghívás típushoz)
    public const ROLE_PHOTO_STUDIO = 'photo_studio';
    public const ROLE_PRINT_SHOP = 'print_shop';

    protected $fillable = [
        'partner_id',
        'code',
        'email',
        'type',
        'role',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Partner aki létrehozta a meghívót
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'partner_id');
    }

    /**
     * User aki elfogadta a meghívót
     */
    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * Generál egy egyedi meghívó kódot (INVITE-XXXXXX formátum)
     */
    public static function generateCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $randomPart = '';
            for ($i = 0; $i < 6; $i++) {
                $randomPart .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $code = 'INVITE-' . $randomPart;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Érvényes-e a meghívó?
     */
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Csapattag meghívó-e?
     */
    public function isTeamMemberInvitation(): bool
    {
        return $this->type === self::TYPE_TEAM_MEMBER;
    }

    /**
     * Partner meghívó-e?
     */
    public function isPartnerInvitation(): bool
    {
        return $this->type === self::TYPE_PARTNER;
    }

    /**
     * Meghívó elfogadása
     */
    public function accept(User $user): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->id,
        ]);
    }

    /**
     * Meghívó visszavonása
     */
    public function revoke(): void
    {
        $this->update(['status' => self::STATUS_REVOKED]);
    }

    /**
     * Lejárt meghívó megjelölése
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Szerepkör magyar megnevezése
     */
    public function getRoleNameAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_DESIGNER => 'Grafikus',
            self::ROLE_MARKETER => 'Marketinges',
            self::ROLE_PRINTER => 'Nyomdász',
            self::ROLE_ASSISTANT => 'Ügyintéző',
            self::ROLE_PHOTO_STUDIO => 'Fotós Partner',
            self::ROLE_PRINT_SHOP => 'Nyomda Partner',
            default => $this->role,
        };
    }

    /**
     * Státusz magyar megnevezése
     */
    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Függőben',
            self::STATUS_ACCEPTED => 'Elfogadva',
            self::STATUS_EXPIRED => 'Lejárt',
            self::STATUS_REVOKED => 'Visszavonva',
            default => $this->status,
        };
    }

    /**
     * Regisztrációs URL generálása
     */
    public function getRegistrationUrl(): string
    {
        return config('app.frontend_partner_url') . '/auth/invite?code=' . $this->code;
    }

    // ============ Scopes ============

    /**
     * Függőben lévő meghívók
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Érvényes meghívók (pending és nem lejárt)
     */
    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Csapattag meghívók
     */
    public function scopeTeamMember($query)
    {
        return $query->where('type', self::TYPE_TEAM_MEMBER);
    }

    /**
     * Partner meghívók
     */
    public function scopePartner($query)
    {
        return $query->where('type', self::TYPE_PARTNER);
    }

    /**
     * Érvényes kód keresése
     */
    public static function findValidCode(string $code): ?self
    {
        return static::valid()
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    /**
     * Csapattag szerepkörök listája
     */
    public static function getTeamMemberRoles(): array
    {
        return [
            self::ROLE_DESIGNER => 'Grafikus',
            self::ROLE_MARKETER => 'Marketinges',
            self::ROLE_PRINTER => 'Nyomdász',
            self::ROLE_ASSISTANT => 'Ügyintéző',
        ];
    }
}
