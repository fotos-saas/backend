<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloPointLog - Pontszám változások naplója
 *
 * @property int $id
 * @property int $tablo_guest_session_id Guest session ID
 * @property string $action Tevékenység típusa
 * @property int $points Pont változás
 * @property string|null $related_type Kapcsolódó model
 * @property int|null $related_id Kapcsolódó rekord ID
 * @property string|null $description Leírás
 * @property \Carbon\Carbon $created_at
 */
class TabloPointLog extends Model
{
    protected $table = 'tablo_point_logs';

    public $timestamps = false;

    public const UPDATED_AT = null;

    // Tevékenység típusok
    public const ACTION_POST = 'post';

    public const ACTION_REPLY = 'reply';

    public const ACTION_LIKE_RECEIVED = 'like_received';

    public const ACTION_LIKE_GIVEN = 'like_given';

    public const ACTION_BADGE = 'badge';

    // Pontértékek (konstansok)
    public const POINTS = [
        self::ACTION_POST => 5,
        self::ACTION_REPLY => 3,
        self::ACTION_LIKE_RECEIVED => 2,
        self::ACTION_LIKE_GIVEN => 1,
    ];

    protected $fillable = [
        'tablo_guest_session_id',
        'action',
        'points',
        'related_type',
        'related_id',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Guest session kapcsolat
     */
    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(TabloGuestSession::class, 'tablo_guest_session_id');
    }

    /**
     * Session szerinti szűrés
     */
    public function scopeForSession(Builder $query, int $sessionId): void
    {
        $query->where('tablo_guest_session_id', $sessionId);
    }

    /**
     * Tevékenység típus szerinti szűrés
     */
    public function scopeAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    /**
     * Időintervallum szerinti szűrés
     */
    public function scopeBetween(Builder $query, $start, $end): void
    {
        $query->whereBetween('created_at', [$start, $end]);
    }
}
