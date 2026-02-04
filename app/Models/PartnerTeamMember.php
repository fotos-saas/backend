<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partner Team Member Model
 *
 * Partner csapattagok pivot tábla.
 * Szabadúszó modell: egy user több partnerhez is tartozhat.
 *
 * @property int $id
 * @property int $partner_id
 * @property int $user_id
 * @property string $role designer | marketer | printer | assistant
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PartnerTeamMember extends Model
{
    use HasFactory;

    // Szerepkörök (megegyeznek a PartnerInvitation-nel)
    public const ROLE_DESIGNER = 'designer';
    public const ROLE_MARKETER = 'marketer';
    public const ROLE_PRINTER = 'printer';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'partner_id',
        'user_id',
        'role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Partner kapcsolat
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(TabloPartner::class, 'partner_id');
    }

    /**
     * User kapcsolat
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            default => $this->role,
        };
    }

    /**
     * Aktiválás
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deaktiválás
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    // ============ Scopes ============

    /**
     * Aktív tagok
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Adott szerepkör
     */
    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Szerepkörök listája
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_DESIGNER => 'Grafikus',
            self::ROLE_MARKETER => 'Marketinges',
            self::ROLE_PRINTER => 'Nyomdász',
            self::ROLE_ASSISTANT => 'Ügyintéző',
        ];
    }
}
