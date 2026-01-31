<?php

namespace App\Models;

use App\Models\Traits\HasTabloAuthor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TabloNewsfeedPost - Hírfolyam bejegyzések.
 *
 * @property int $id
 * @property int $tablo_project_id
 * @property string $author_type 'contact' vagy 'guest'
 * @property int $author_id
 * @property string $post_type 'announcement' vagy 'event'
 * @property string $title
 * @property string|null $content
 * @property \Carbon\Carbon|null $event_date
 * @property string|null $event_time
 * @property string|null $event_location
 * @property bool $is_pinned
 * @property int $likes_count
 * @property int $comments_count
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloNewsfeedPost extends Model
{
    use HasFactory, SoftDeletes, HasTabloAuthor;

    public const POST_TYPE_ANNOUNCEMENT = 'announcement';

    public const POST_TYPE_EVENT = 'event';

    public const AUTHOR_TYPE_CONTACT = 'contact';

    public const AUTHOR_TYPE_GUEST = 'guest';

    protected $fillable = [
        'tablo_project_id',
        'author_type',
        'author_id',
        'post_type',
        'title',
        'content',
        'event_date',
        'event_time',
        'event_location',
        'is_pinned',
        'likes_count',
        'comments_count',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'event_date' => 'date',
        ];
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    // Author methods provided by HasTabloAuthor trait:
    // - getAuthorModelAttribute()
    // - getAuthorNameAttribute()
    // - isAuthorContact()
    // - isAuthorGuest()
    // - isAuthoredBy()

    /**
     * Check if post is event type
     */
    public function getIsEventAttribute(): bool
    {
        return $this->post_type === self::POST_TYPE_EVENT;
    }

    /**
     * Check if post is announcement type
     */
    public function getIsAnnouncementAttribute(): bool
    {
        return $this->post_type === self::POST_TYPE_ANNOUNCEMENT;
    }

    /**
     * Get media files
     */
    public function media(): HasMany
    {
        return $this->hasMany(TabloNewsfeedMedia::class, 'tablo_newsfeed_post_id')
            ->orderBy('sort_order');
    }

    /**
     * Get comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TabloNewsfeedComment::class, 'tablo_newsfeed_post_id')
            ->orderBy('created_at');
    }

    /**
     * Get likes
     */
    public function likes(): HasMany
    {
        return $this->hasMany(TabloNewsfeedLike::class, 'tablo_newsfeed_post_id');
    }

    /**
     * Check if user has liked this post
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
     * Toggle reaction on post
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
                $this->updateLikesCount();

                return [
                    'has_reacted' => false,
                    'user_reaction' => null,
                    'reactions' => $this->getReactionsSummary(),
                    'likes_count' => $this->fresh()->likes_count,
                ];
            }

            // Ha másik reakció, cseréljük
            $existingLike->update(['reaction' => $reaction]);

            return [
                'has_reacted' => true,
                'user_reaction' => $reaction,
                'reactions' => $this->getReactionsSummary(),
                'likes_count' => $this->likes_count,
            ];
        }

        // Új reakció létrehozása
        TabloNewsfeedLike::create([
            'tablo_newsfeed_post_id' => $this->id,
            'liker_type' => $likerType,
            'liker_id' => $likerId,
            'reaction' => $reaction,
            'created_at' => now(),
        ]);

        $this->updateLikesCount();

        return [
            'has_reacted' => true,
            'user_reaction' => $reaction,
            'reactions' => $this->getReactionsSummary(),
            'likes_count' => $this->fresh()->likes_count,
        ];
    }

    /**
     * Update likes count cache
     */
    public function updateLikesCount(): void
    {
        $this->update(['likes_count' => $this->likes()->count()]);
    }

    /**
     * Update comments count cache
     */
    public function updateCommentsCount(): void
    {
        $this->update(['comments_count' => $this->comments()->count()]);
    }

    /**
     * Pin post
     */
    public function pin(): void
    {
        $this->update(['is_pinned' => true]);
    }

    /**
     * Unpin post
     */
    public function unpin(): void
    {
        $this->update(['is_pinned' => false]);
    }

    /**
     * Scope for pinned posts
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for announcements
     */
    public function scopeAnnouncements($query)
    {
        return $query->where('post_type', self::POST_TYPE_ANNOUNCEMENT);
    }

    /**
     * Scope for events
     */
    public function scopeEvents($query)
    {
        return $query->where('post_type', self::POST_TYPE_EVENT);
    }

    /**
     * Scope for upcoming events
     */
    public function scopeUpcomingEvents($query)
    {
        return $query->where('post_type', self::POST_TYPE_EVENT)
            ->whereNotNull('event_date')
            ->where('event_date', '>=', now()->toDateString());
    }

    /**
     * Scope ordered by activity (pinned first, then by date)
     */
    public function scopeOrderedByActivity($query)
    {
        return $query->orderByDesc('is_pinned')
            ->orderByDesc('created_at');
    }

    /**
     * Check if author can edit this post
     */
    public function canBeEditedBy(string $authorType, int $authorId): bool
    {
        return $this->author_type === $authorType && $this->author_id === $authorId;
    }
}
