<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Granular Permission Service for Filament Resources
 *
 * Provides centralized permission checking with caching and wildcard support.
 */
class PermissionService
{
    /**
     * Check if the current user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        // Super Admin bypass - everything is allowed
        if ($user->hasRole(User::ROLE_SUPER_ADMIN)) {
            $this->logDebug($permission, true, 'Super Admin bypass');

            return true;
        }

        $userPermissions = $this->getCachedPermissions($user);

        // Exact match
        if ($userPermissions->contains($permission)) {
            $this->logDebug($permission, true, 'Exact match');

            return true;
        }

        // Wildcard match: work-sessions.* matches work-sessions.view, work-sessions.edit, etc.
        if ($this->checkWildcardMatch($permission, $userPermissions)) {
            $this->logDebug($permission, true, 'Wildcard match');

            return true;
        }

        // Global wildcard
        if ($userPermissions->contains('*')) {
            $this->logDebug($permission, true, 'Global wildcard match');

            return true;
        }

        $this->logDebug($permission, false, 'No match found');

        return false;
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user can access a specific tab within a resource.
     */
    public function canAccessTab(string $resource, string $tabName): bool
    {
        return $this->hasPermission("{$resource}.tab.{$tabName}");
    }

    /**
     * Check if user can see a specific field within a resource.
     */
    public function canSeeField(string $resource, string $fieldName): bool
    {
        return $this->hasPermission("{$resource}.field.{$fieldName}");
    }

    /**
     * Check if user can access a specific action within a resource.
     */
    public function canAccessAction(string $resource, string $actionName): bool
    {
        return $this->hasPermission("{$resource}.action.{$actionName}");
    }

    /**
     * Check if user can access a specific relation manager within a resource.
     */
    public function canAccessRelation(string $resource, string $relationName): bool
    {
        return $this->hasPermission("{$resource}.relation.{$relationName}");
    }

    /**
     * Get all permissions for a specific resource.
     */
    public function getResourcePermissions(string $resource): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect([]);
        }

        $allPermissions = $this->getCachedPermissions($user);

        return $allPermissions->filter(function ($permission) use ($resource) {
            return str_starts_with($permission, $resource.'.');
        });
    }

    /**
     * Refresh cached permissions for a specific user.
     */
    public function refreshPermissions(User $user): void
    {
        Cache::forget($this->getCacheKey($user));
    }

    /**
     * Refresh cached permissions for all users with a specific role.
     */
    public function refreshRolePermissions(\Spatie\Permission\Models\Role $role): void
    {
        $role->users()->each(function ($user) {
            $this->refreshPermissions($user);
        });
    }

    /**
     * Get cached permissions for a user.
     */
    protected function getCachedPermissions(User $user): Collection
    {
        if (! config('filament-permissions.cache.enabled', true)) {
            return $this->fetchUserPermissions($user);
        }

        $cacheKey = $this->getCacheKey($user);
        $ttl = config('filament-permissions.cache.ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($user) {
            return $this->fetchUserPermissions($user);
        });
    }

    /**
     * Fetch user permissions from the database via Spatie Permission.
     */
    protected function fetchUserPermissions(User $user): Collection
    {
        // Get all permissions via roles and direct permissions
        return $user->getAllPermissions()->pluck('name');
    }

    /**
     * Check if permission matches any wildcard patterns.
     */
    protected function checkWildcardMatch(string $permission, Collection $userPermissions): bool
    {
        $parts = explode('.', $permission);

        // Try progressively broader wildcards
        // e.g., for "work-sessions.tab.coupon-settings":
        // - work-sessions.tab.*
        // - work-sessions.*
        for ($i = count($parts) - 1; $i > 0; $i--) {
            $wildcard = implode('.', array_slice($parts, 0, $i)).'.*';
            if ($userPermissions->contains($wildcard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate cache key for user permissions.
     */
    protected function getCacheKey(User $user): string
    {
        $prefix = config('filament-permissions.cache.key_prefix', 'filament_permissions_');

        return $prefix.$user->id;
    }

    /**
     * Log debug information if debug mode is enabled.
     */
    protected function logDebug(string $permission, bool $result, string $reason): void
    {
        if (config('filament-permissions.debug', false)) {
            $status = $result ? 'GRANTED' : 'DENIED';
            $user = auth()->user();
            $userId = $user ? $user->id : 'guest';

            Log::debug("Permission check: {$permission} = {$status} (User: {$userId}, Reason: {$reason})");
        }
    }
}

