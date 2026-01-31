<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TabloPollOption - Szavazási opciók.
 *
 * @property int $id
 * @property int $tablo_poll_id
 * @property int|null $tablo_sample_template_id Ha sablon választás
 * @property string $label Opció neve
 * @property string|null $description
 * @property string|null $image_url Preview kép
 * @property int $display_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TabloPollOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablo_poll_id',
        'tablo_sample_template_id',
        'label',
        'description',
        'image_url',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    /**
     * Get the poll
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(TabloPoll::class, 'tablo_poll_id');
    }

    /**
     * Get the template (if template option)
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TabloSampleTemplate::class, 'tablo_sample_template_id');
    }

    /**
     * Get votes for this option
     */
    public function votes(): HasMany
    {
        return $this->hasMany(TabloPollVote::class, 'tablo_poll_option_id');
    }

    /**
     * Get votes count.
     *
     * Uses votes_count attribute if loaded via withCount('votes'),
     * otherwise falls back to query.
     */
    public function getVotesCountAttribute(): int
    {
        // Ha már eager loading-gal be van töltve
        if (isset($this->attributes['votes_count'])) {
            return (int) $this->attributes['votes_count'];
        }

        return $this->votes()->count();
    }

    /**
     * Calculate percentage of total votes.
     *
     * @deprecated Use PollService::getResults() instead which calculates this efficiently.
     *             This method causes N+1 query when accessing poll->total_votes.
     *
     * @param  int|null  $totalVotes  Optionally provide total votes to avoid N+1 query
     */
    public function calculatePercentage(?int $totalVotes = null): float
    {
        $total = $totalVotes ?? $this->poll->total_votes;

        if ($total === 0) {
            return 0;
        }

        return round(($this->votes_count / $total) * 100, 1);
    }

    /**
     * Get percentage of total votes.
     *
     * @deprecated Use calculatePercentage() or PollService::getResults() instead.
     */
    public function getPercentageAttribute(): float
    {
        return $this->calculatePercentage();
    }

    /**
     * Get display image URL (from template or custom)
     */
    public function getDisplayImageUrlAttribute(): ?string
    {
        if ($this->template) {
            return $this->template->thumbnail_url;
        }

        return $this->image_url;
    }

    /**
     * Scope ordered by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
