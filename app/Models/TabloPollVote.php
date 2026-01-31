<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloPollVote - Leadott szavazatok.
 *
 * @property int $id
 * @property int $tablo_poll_id
 * @property int $tablo_poll_option_id
 * @property int $tablo_guest_session_id
 * @property \Carbon\Carbon $voted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPollVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_poll_id',
        'tablo_poll_option_id',
        'tablo_guest_session_id',
        'voted_at',
    ];

    protected function casts(): array
    {
        return [
            'voted_at' => 'datetime',
        ];
    }

    /**
     * Boot: automatikus voted_at beállítás
     */
    protected static function booted(): void
    {
        static::creating(function (TabloPollVote $vote) {
            if (empty($vote->voted_at)) {
                $vote->voted_at = now();
            }
        });
    }

    /**
     * Get the poll
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(TabloPoll::class, 'tablo_poll_id');
    }

    /**
     * Get the selected option
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(TabloPollOption::class, 'tablo_poll_option_id');
    }

    /**
     * Get the guest who voted
     */
    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'tablo_guest_session_id');
    }

    /**
     * Get voter name via guest session
     */
    public function getVoterNameAttribute(): string
    {
        return $this->guestSession?->guest_name ?? 'Ismeretlen';
    }
}
