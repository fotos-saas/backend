<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloNewsfeedCommentLike - Hírfolyam komment reakciók.
 *
 * @property int $id
 * @property int $tablo_newsfeed_comment_id
 * @property string $liker_type 'contact' vagy 'guest'
 * @property int $liker_id
 * @property string $reaction Emoji reakció
 * @property \Carbon\Carbon $created_at
 */
class TabloNewsfeedCommentLike extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const LIKER_TYPE_CONTACT = 'contact';

    public const LIKER_TYPE_GUEST = 'guest';

    /** Támogatott reakciók */
    public const REACTIONS = ['💀', '😭', '🫡', '❤️', '👀'];

    public const DEFAULT_REACTION = '❤️';

    protected $fillable = [
        'tablo_newsfeed_comment_id',
        'liker_type',
        'liker_id',
        'reaction',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the comment
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TabloNewsfeedComment::class, 'tablo_newsfeed_comment_id');
    }

    /**
     * Get liker model
     */
    public function getLikerModelAttribute(): ?Model
    {
        if ($this->liker_type === self::LIKER_TYPE_CONTACT) {
            return TabloContact::find($this->liker_id);
        }

        if ($this->liker_type === self::LIKER_TYPE_GUEST) {
            return TabloGuestSession::find($this->liker_id);
        }

        return null;
    }

    /**
     * Get liker name
     */
    public function getLikerNameAttribute(): string
    {
        $liker = $this->liker_model;

        if ($liker instanceof TabloContact) {
            return $liker->name;
        }

        if ($liker instanceof TabloGuestSession) {
            return $liker->guest_name;
        }

        return 'Ismeretlen';
    }
}
