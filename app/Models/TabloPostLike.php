<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TabloPostLike - Hozzászólás reakciók.
 *
 * @property int $id
 * @property int $tablo_discussion_post_id
 * @property string $liker_type 'contact' vagy 'guest'
 * @property int $liker_id Polymorphic ID
 * @property string $reaction Emoji reakció (💀 😭 🫡 ❤️ 👀)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPostLike extends Model
{
    use HasFactory;

    public const LIKER_TYPE_CONTACT = 'contact';

    public const LIKER_TYPE_GUEST = 'guest';

    /**
     * Támogatott reakciók
     */
    public const REACTIONS = ['💀', '😭', '🫡', '❤️', '👀'];

    /**
     * Default reakció (like)
     */
    public const DEFAULT_REACTION = '❤️';

    protected $fillable = [
        'tablo_discussion_post_id',
        'liker_type',
        'liker_id',
        'reaction',
    ];

    /**
     * Get the post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(TabloDiscussionPost::class, 'tablo_discussion_post_id');
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
