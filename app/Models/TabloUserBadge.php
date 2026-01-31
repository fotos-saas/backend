<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloUserBadge - Felhasználó által megszerzett badge
 *
 * @property int $id
 * @property int $tablo_guest_session_id Guest session ID
 * @property int $tablo_badge_id Badge ID
 * @property \Carbon\Carbon $earned_at Megszerzés időpontja
 * @property bool $is_new Új badge (még nem látta)
 * @property \Carbon\Carbon|null $viewed_at Megtekintés időpontja
 */
class TabloUserBadge extends Model
{
    protected $table = 'tablo_user_badges';

    protected $fillable = [
        'tablo_guest_session_id',
        'tablo_badge_id',
        'earned_at',
        'is_new',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'viewed_at' => 'datetime',
            'is_new' => 'boolean',
        ];
    }

    /**
     * Badge kapcsolat
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(TabloBadge::class, 'tablo_badge_id');
    }

    /**
     * Guest session kapcsolat
     */
    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'tablo_guest_session_id');
    }

    /**
     * Csak új badge-ek
     */
    public function scopeNew(Builder $query): void
    {
        $query->where('is_new', true);
    }

    /**
     * Session szerinti szűrés
     */
    public function scopeForSession(Builder $query, int $sessionId): void
    {
        $query->where('tablo_guest_session_id', $sessionId);
    }

    /**
     * Badge megtekintése (is_new = false)
     */
    public function markAsViewed(): void
    {
        $this->update([
            'is_new' => false,
            'viewed_at' => now(),
        ]);
    }
}
