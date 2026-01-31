<?php

namespace App\Models;

use App\Models\Traits\HasTabloAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TabloDiscussionPost - Hozzászólások.
 *
 * @property int $id
 * @property int $tablo_discussion_id
 * @property int|null $parent_id Thread válasz
 * @property string $author_type 'contact' vagy 'guest'
 * @property int $author_id Polymorphic ID
 * @property string $content
 * @property array|null $mentions [@név1, @név2]
 * @property bool $is_edited
 * @property \Carbon\Carbon|null $edited_at
 * @property int $likes_count Cache
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloDiscussionPost extends Model
{
    use HasFactory, SoftDeletes, HasTabloAuthor;

    public const AUTHOR_TYPE_CONTACT = 'contact';

    public const AUTHOR_TYPE_GUEST = 'guest';

    /**
     * Edit time limit in minutes
     */
    public const EDIT_TIME_LIMIT_MINUTES = 15;

    protected $fillable = [
        'tablo_discussion_id',
        'parent_id',
        'author_type',
        'author_id',
        'content',
        'mentions',
        'is_edited',
        'edited_at',
        'likes_count',
    ];

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
            'likes_count' => 'integer',
        ];
    }

    /**
     * Boot: update parent discussion on create
     */
    protected static function booted(): void
    {
        static::created(function (TabloDiscussionPost $post) {
            $post->discussion->updatePostsCount();
            $post->discussion->touch();
        });

        static::deleted(function (TabloDiscussionPost $post) {
            $post->discussion->updatePostsCount();
        });
    }

    /**
     * Get the discussion
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(TabloDiscussion::class, 'tablo_discussion_id');
    }

    /**
     * Get parent post (if reply)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TabloDiscussionPost::class, 'parent_id');
    }

    /**
     * Get replies to this post
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TabloDiscussionPost::class, 'parent_id')
            ->orderBy('created_at');
    }

    /**
     * Get likes
     */
    public function likes(): HasMany
    {
        return $this->hasMany(TabloPostLike::class, 'tablo_discussion_post_id');
    }

    /**
     * Get media attachments
     */
    public function media(): HasMany
    {
        return $this->hasMany(TabloPostMedia::class, 'tablo_discussion_post_id');
    }

    // Author methods provided by HasTabloAuthor trait:
    // - getAuthorModelAttribute()
    // - getAuthorNameAttribute()
    // - isAuthorContact()
    // - isAuthorGuest()
    // - isAuthoredBy()

    /**
     * Check if post is a reply
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if user can edit (within time limit)
     */
    public function canEdit(): bool
    {
        return $this->created_at->diffInMinutes(now()) <= self::EDIT_TIME_LIMIT_MINUTES;
    }

    /**
     * Edit content
     */
    public function editContent(string $newContent): void
    {
        $this->update([
            'content' => $newContent,
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }

    /**
     * Check if liked by user (any reaction)
     */
    public function isLikedBy(string $likerType, int $likerId): bool
    {
        return $this->likes()
            ->where('liker_type', $likerType)
            ->where('liker_id', $likerId)
            ->exists();
    }

    /**
     * Get user's reaction (if any)
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
     * Get all reactions grouped by emoji with count
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
     * Toggle reaction (add/remove/change)
     *
     * @return array{added: bool, reaction: string|null, oldReaction: string|null}
     */
    public function toggleReaction(string $likerType, int $likerId, string $reaction = '❤️'): array
    {
        $existing = $this->likes()
            ->where('liker_type', $likerType)
            ->where('liker_id', $likerId)
            ->first();

        // Ha ugyanaz a reakció, töröljük
        if ($existing && $existing->reaction === $reaction) {
            $existing->delete();
            $this->decrement('likes_count');

            return ['added' => false, 'reaction' => null, 'oldReaction' => $reaction];
        }

        // Ha más reakció volt, frissítjük
        if ($existing) {
            $oldReaction = $existing->reaction;
            $existing->update(['reaction' => $reaction]);

            return ['added' => true, 'reaction' => $reaction, 'oldReaction' => $oldReaction];
        }

        // Új reakció
        TabloPostLike::create([
            'tablo_discussion_post_id' => $this->id,
            'liker_type' => $likerType,
            'liker_id' => $likerId,
            'reaction' => $reaction,
        ]);
        $this->increment('likes_count');

        return ['added' => true, 'reaction' => $reaction, 'oldReaction' => null];
    }

    /**
     * Toggle like (legacy - uses default ❤️ reaction)
     * @deprecated Use toggleReaction() instead
     */
    public function toggleLike(string $likerType, int $likerId): bool
    {
        $result = $this->toggleReaction($likerType, $likerId, TabloPostLike::DEFAULT_REACTION);

        return $result['added'];
    }

    /**
     * Scope for root posts (not replies)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for replies
     */
    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Parse mentions from content
     */
    public static function parseMentions(string $content): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);

        return $matches[1] ?? [];
    }
}
