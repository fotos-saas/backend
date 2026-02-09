<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HelpTourProgress - User túra haladás.
 *
 * @property int $id
 * @property int $user_id
 * @property int $help_tour_id
 * @property string $status
 * @property int $last_step_number
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpTourProgress extends Model
{
    protected $table = 'help_tour_progress';

    protected $fillable = [
        'user_id',
        'help_tour_id',
        'status',
        'last_step_number',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_step_number' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(HelpTour::class, 'help_tour_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
