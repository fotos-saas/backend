<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TabloPoll - Szavazás a tablómintákra.
 *
 * @property int $id
 * @property int $tablo_project_id
 * @property int|null $creator_contact_id
 * @property string $title
 * @property string|null $description
 * @property string|null $cover_image_url
 * @property string $type 'template' vagy 'custom'
 * @property bool $is_free_choice Bármelyik sablont választhatják
 * @property bool $is_active
 * @property bool $is_multiple_choice
 * @property int $max_votes_per_guest
 * @property bool $show_results_before_vote
 * @property bool $use_for_finalization Véglegesítéshez használt
 * @property \Carbon\Carbon|null $close_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPoll extends Model
{
    use HasFactory;

    public const TYPE_TEMPLATE = 'template';

    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'tablo_project_id',
        'creator_contact_id',
        'title',
        'description',
        'cover_image_url',
        'type',
        'is_free_choice',
        'is_active',
        'is_multiple_choice',
        'max_votes_per_guest',
        'show_results_before_vote',
        'use_for_finalization',
        'close_at',
    ];

    protected function casts(): array
    {
        return [
            'is_free_choice' => 'boolean',
            'is_active' => 'boolean',
            'is_multiple_choice' => 'boolean',
            'max_votes_per_guest' => 'integer',
            'show_results_before_vote' => 'boolean',
            'use_for_finalization' => 'boolean',
            'close_at' => 'datetime',
        ];
    }

    /**
     * Get the project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TabloProject::class, 'tablo_project_id');
    }

    /**
     * Get the creator contact
     */
    public function creatorContact(): BelongsTo
    {
        return $this->belongsTo(TabloContact::class, 'creator_contact_id');
    }

    /**
     * Get poll options
     */
    public function options(): HasMany
    {
        return $this->hasMany(TabloPollOption::class, 'tablo_poll_id')
            ->orderBy('display_order');
    }

    /**
     * Get all votes
     */
    public function votes(): HasMany
    {
        return $this->hasMany(TabloPollVote::class, 'tablo_poll_id');
    }

    /**
     * Get media files
     */
    public function media(): HasMany
    {
        return $this->hasMany(TabloPollMedia::class, 'tablo_poll_id')->orderBy('sort_order');
    }

    /**
     * Check if poll is open for voting
     */
    public function isOpen(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->close_at && $this->close_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if poll is closed
     */
    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Get total votes count.
     *
     * Uses votes_count attribute if loaded via withCount('votes'),
     * otherwise falls back to query.
     */
    public function getTotalVotesAttribute(): int
    {
        // Ha már eager loading-gal be van töltve
        if (isset($this->attributes['votes_count'])) {
            return (int) $this->attributes['votes_count'];
        }

        return $this->votes()->count();
    }

    /**
     * Get unique voters count.
     *
     * Uses unique_voters_count attribute if loaded via subquery,
     * otherwise falls back to query.
     */
    public function getUniqueVotersCountAttribute(): int
    {
        // Ha már eager loading-gal be van töltve
        if (isset($this->attributes['unique_voters_count'])) {
            return (int) $this->attributes['unique_voters_count'];
        }

        return $this->votes()->distinct('tablo_guest_session_id')->count('tablo_guest_session_id');
    }

    /**
     * Check if a guest already voted
     */
    public function hasGuestVoted(int $guestSessionId): bool
    {
        return $this->votes()->where('tablo_guest_session_id', $guestSessionId)->exists();
    }

    /**
     * Get votes count for a guest
     */
    public function getGuestVotesCount(int $guestSessionId): int
    {
        return $this->votes()->where('tablo_guest_session_id', $guestSessionId)->count();
    }

    /**
     * Can guest vote more?
     */
    public function canGuestVote(int $guestSessionId): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $currentVotes = $this->getGuestVotesCount($guestSessionId);

        return $currentVotes < $this->max_votes_per_guest;
    }

    /**
     * Get winning option(s).
     *
     * @deprecated Use PollService::getWinners() instead for better performance.
     */
    public function getWinningOptionsAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        $options = $this->options()->withCount('votes')->get();

        if ($options->isEmpty()) {
            return collect();
        }

        $maxVotes = $options->max('votes_count');

        if ($maxVotes === 0) {
            return collect();
        }

        return $options->filter(fn ($option) => $option->votes_count === $maxVotes);
    }

    /**
     * Scope for active polls
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('close_at')
                    ->orWhere('close_at', '>', now());
            });
    }

    /**
     * Scope for closed polls
     */
    public function scopeClosed($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', false)
                ->orWhere('close_at', '<=', now());
        });
    }

    /**
     * Scope for template type polls
     */
    public function scopeTemplateType($query)
    {
        return $query->where('type', self::TYPE_TEMPLATE);
    }

    /**
     * Scope for finalization polls
     */
    public function scopeForFinalization($query)
    {
        return $query->where('use_for_finalization', true);
    }
}
