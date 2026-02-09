<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HelpTour - Tutorial definíciók.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $trigger_route
 * @property array $target_roles
 * @property array $target_plans
 * @property string $trigger_type
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class HelpTour extends Model
{
    protected $fillable = [
        'key',
        'title',
        'trigger_route',
        'target_roles',
        'target_plans',
        'trigger_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'target_plans' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(HelpTourStep::class)->orderBy('step_number');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(HelpTourProgress::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRoute($query, string $route)
    {
        return $query->where('trigger_route', $route);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->whereJsonContains('target_roles', $role);
    }

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
