<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Navigation configuration for role-specific menu customization.
 *
 * Stores custom navigation settings per role including label overrides,
 * group assignments, sort order, and visibility.
 */
class NavigationConfiguration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'resource_key',
        'label',
        'navigation_group',
        'sort_order',
        'is_visible',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the role that owns this navigation configuration.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
