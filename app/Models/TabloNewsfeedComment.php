<?php

namespace App\Models;

use App\Models\Traits\HasTabloAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TabloNewsfeedComment - Hírfolyam kommentek.
 *
 * @property int $id
 * @property int $tablo_newsfeed_post_id
 * @property int|null $parent_id Szülő komment ID (válasz esetén)
 * @property string $author_type 'contact' vagy 'guest'
 * @property int $author_id
 * @property string $content
 * @property bool $is_edited
 * @property \Carbon\Carbon|null $edited_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloNewsfeedComment extends Model
{
    use HasFactory, SoftDeletes, HasTabloAuthor;

    public const AUTHOR_TYPE_CONTACT = 'contact';

    public const AUTHOR_TYPE_GUEST = 'guest';

    protected $fillable = [
        'tablo_newsfeed_post_id',
        'parent_id',
        'author_type',
        'author_id',
        'content',
        'is_edited',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    /**
     * Get the post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(TabloNewsfeedPost::class, 'tablo_newsfeed_post_id');
    }

    /**
     * Get parent comment (if this is a reply)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    /**
     * Check if this is a reply
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    // Author methods provided by HasTabloAuthor trait:
    // - getAuthorModelAttribute()
    // - getAuthorNameAttribute()
    // - isAuthorContact()
    // - isAuthorGuest()
    // - isAuthoredBy()

    /**
     * Check if author can delete this comment
     */
    public function canBeDeletedBy(string $authorType, int $authorId): bool
    {
        return $this->isAuthoredBy($authorType, $authorId);
    }

    /**
     * Mark as edited
     */
    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }

    /**
     * Get likes/reactions
     */
    public function likes(): HasMany
    {
        return $this->hasMany(TabloNewsfeedCommentLike::class, 'tablo_newsfeed_comment_id');
    }

    /**
     * Check if user has reacted to this comment
     */
    public function hasLiked(string $likerType, int $likerId): bool
    {
        return $this->likes()
            ->where('liker_type', $likerType)
            ->where('liker_id', $likerId)
            ->exists();
    }

    /**
     * Get user's current reaction (if any)
     */
    public function getUserReaction(string $likerType, int $likerId): ?string
    {
        $like = $this->likes()
            ->where('liker_type', $likerType)
            ->where('liker_id', $likerId)
            ->first();

        return $like?->reaction;
    }

    /**
     * Get reactions summary { emoji: count }
     */
    public function getReactionsSummary(): array
    {
        return $this->likes()
            ->selectRaw('reaction, COUNT(*) as count')
            ->groupBy('reaction')
            ->pluck('count', 'reaction')
            ->toArray();
    }

    /**
     * Toggle reaction on comment
     */
    public function toggleReaction(string $likerType, int $likerId, string $reaction = '❤️'): array
    {
        $existingLike = $this->likes()
            ->where('liker_type', $likerType)
            ->where('liker_id', $likerId)
            ->first();

        if ($existingLike) {
            // Ha ugyanaz a reakció, töröljük
            if ($existingLike->reaction === $reaction) {
                $existingLike->delete();

                return [
                    'has_reacted' => false,
                    'user_reaction' => null,
                    'reactions' => $this->getReactionsSummary(),
                    'likes_count' => $this->likes()->count(),
                ];
            }

            // Ha másik reakció, cseréljük
            $existingLike->update(['reaction' => $reaction]);

            return [
                'has_reacted' => true,
                'user_reaction' => $reaction,
                'reactions' => $this->getReactionsSummary(),
                'likes_count' => $this->likes()->count(),
            ];
        }

        // Új reakció létrehozása
        TabloNewsfeedCommentLike::create([
            'tablo_newsfeed_comment_id' => $this->id,
            'liker_type' => $likerType,
            'liker_id' => $likerId,
            'reaction' => $reaction,
            'created_at' => now(),
        ]);

        return [
            'has_reacted' => true,
            'user_reaction' => $reaction,
            'reactions' => $this->getReactionsSummary(),
            'likes_count' => $this->likes()->count(),
        ];
    }
}
