<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HelpTourStep - Tutorial lépések.
 *
 * @property int $id
 * @property int $help_tour_id
 * @property int $step_number
 * @property string $title
 * @property string $content
 * @property string|null $target_selector
 * @property string $placement
 * @property string $highlight_type
 * @property bool $allow_skip
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpTourStep extends Model
{
    protected $fillable = [
        'help_tour_id',
        'step_number',
        'title',
        'content',
        'target_selector',
        'placement',
        'highlight_type',
        'allow_skip',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'allow_skip' => 'boolean',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(HelpTour::class, 'help_tour_id');
    }
}
