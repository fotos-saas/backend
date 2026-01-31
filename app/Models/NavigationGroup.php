<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Navigation group for organizing menu items.
 *
 * Defines navigation groups (e.g., "Platform Settings", "Shipping & Payment")
 * that can be customized per role or set as default for all roles.
 */
class NavigationGroup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'key',
        'label',
        'sort_order',
        'is_system',
        'collapsed',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'collapsed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the role that owns this navigation group.
     * Returns null if this is a default group for all roles.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
