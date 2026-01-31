<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * TabloDiscussion - Fórum beszélgetések.
 *
 * @property int $id
 * @property int $tablo_project_id
 * @property int|null $tablo_sample_template_id Sablonhoz kapcsolva
 * @property string $creator_type 'contact' vagy 'guest'
 * @property int $creator_id Polymorphic ID
 * @property string $title
 * @property string $slug
 * @property bool $is_pinned
 * @property bool $is_locked Nincs új hozzászólás
 * @property int $posts_count Cache
 * @property int $views_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloDiscussion extends Model
{
    use HasFactory;

    public const CREATOR_TYPE_CONTACT = 'contact';

    public const CREATOR_TYPE_GUEST = 'guest';

    protected $fillable = [
        'tablo_project_id',
        'tablo_sample_template_id',
        'creator_type',
        'creator_id',
        'title',
        'slug',
        'is_pinned',
        'is_locked',
        'posts_count',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'posts_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /**
     * Boot: automatikus slug generálás
     */
    protected static function booted(): void
    {
        static::creating(function (TabloDiscussion $discussion) {
            if (empty($discussion->slug)) {
                $discussion->slug = static::generateUniqueSlug($discussion->title);
            }
        });
    }

    /**
     * Generate unique slug
     */
    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get the related template (optional)
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TabloSampleTemplate::class, 'tablo_sample_template_id');
    }

    /**
     * Get creator (polymorphic)
     */
    public function creator(): MorphTo
    {
        return $this->morphTo('creator', 'creator_type', 'creator_id');
    }

    /**
     * Get creator model class
     */
    public function getCreatorModelAttribute(): ?Model
    {
        if ($this->creator_type === self::CREATOR_TYPE_CONTACT) {
            return TabloContact::find($this->creator_id);
        }

        if ($this->creator_type === self::CREATOR_TYPE_GUEST) {
            return TabloGuestSession::find($this->creator_id);
        }

        return null;
    }

    /**
     * Get creator name
     */
    public function getCreatorNameAttribute(): string
    {
        $creator = $this->creator_model;

        if ($creator instanceof TabloContact) {
            return $creator->name;
        }

        if ($creator instanceof TabloGuestSession) {
            return $creator->guest_name;
        }

        return 'Ismeretlen';
    }

    /**
     * Is creator a contact?
     */
    public function isCreatorContact(): bool
    {
        return $this->creator_type === self::CREATOR_TYPE_CONTACT;
    }

    /**
     * Is creator a guest?
     */
    public function isCreatorGuest(): bool
    {
        return $this->creator_type === self::CREATOR_TYPE_GUEST;
    }

    /**
     * Get all posts
     */
    public function posts(): HasMany
    {
        return $this->hasMany(TabloDiscussionPost::class, 'tablo_discussion_id');
    }

    /**
     * Get root posts (no parent)
     */
    public function rootPosts(): HasMany
    {
        return $this->posts()->whereNull('parent_id')->orderBy('created_at');
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Update posts count cache
     */
    public function updatePostsCount(): void
    {
        $this->update(['posts_count' => $this->posts()->count()]);
    }

    /**
     * Lock discussion
     */
    public function lock(): void
    {
        $this->update(['is_locked' => true]);
    }

    /**
     * Unlock discussion
     */
    public function unlock(): void
    {
        $this->update(['is_locked' => false]);
    }

    /**
     * Pin discussion
     */
    public function pin(): void
    {
        $this->update(['is_pinned' => true]);
    }

    /**
     * Unpin discussion
     */
    public function unpin(): void
    {
        $this->update(['is_pinned' => false]);
    }

    /**
     * Check if can add new posts
     */
    public function canAddPosts(): bool
    {
        return ! $this->is_locked;
    }

    /**
     * Get last post
     */
    public function getLastPostAttribute(): ?TabloDiscussionPost
    {
        return $this->posts()->latest()->first();
    }

    /**
     * Scope for pinned discussions
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for unlocked discussions
     */
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Scope for discussions ordered by activity
     */
    public function scopeOrderedByActivity($query)
    {
        return $query->orderByDesc('is_pinned')
            ->orderByDesc('updated_at');
    }

    /**
     * Scope for discussions by template
     */
    public function scopeForTemplate($query, int $templateId)
    {
        return $query->where('tablo_sample_template_id', $templateId);
    }

    /**
     * Find by slug
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
