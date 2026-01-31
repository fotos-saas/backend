<?php

namespace App\Observers;

use App\Services\PermissionService;
use Spatie\Permission\Models\Role;

/**
 * Observer for Role model to handle cache invalidation.
 *
 * When a role is updated or deleted, this observer ensures
 * that all cached permissions for users with that role are invalidated.
 */
class RoleObserver
{
    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        $this->refreshRolePermissions($role);
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        $this->refreshRolePermissions($role);
    }

    /**
     * Refresh cached permissions for all users with this role.
     */
    protected function refreshRolePermissions(Role $role): void
    {
        app(PermissionService::class)->refreshRolePermissions($role);
    }
}

