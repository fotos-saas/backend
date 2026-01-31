<?php

/**
 * Global Helper Functions for Granular Permissions
 *
 * These functions provide a convenient way to check permissions
 * throughout the application without verbose service calls.
 */

use App\Services\PermissionService;

if (! function_exists('can_access_permission')) {
    /**
     * Check if the current user has a specific permission.
     */
    function can_access_permission(string $permission): bool
    {
        return app(PermissionService::class)->hasPermission($permission);
    }
}

if (! function_exists('can_access_tab')) {
    /**
     * Check if the current user can access a specific tab within a resource.
     */
    function can_access_tab(string $resource, string $tab): bool
    {
        return app(PermissionService::class)->canAccessTab($resource, $tab);
    }
}

if (! function_exists('can_see_field')) {
    /**
     * Check if the current user can see a specific field within a resource.
     */
    function can_see_field(string $resource, string $field): bool
    {
        return app(PermissionService::class)->canSeeField($resource, $field);
    }
}

if (! function_exists('can_access_action')) {
    /**
     * Check if the current user can access a specific action within a resource.
     */
    function can_access_action(string $resource, string $action): bool
    {
        return app(PermissionService::class)->canAccessAction($resource, $action);
    }
}

if (! function_exists('can_access_relation')) {
    /**
     * Check if the current user can access a specific relation manager within a resource.
     */
    function can_access_relation(string $resource, string $relation): bool
    {
        return app(PermissionService::class)->canAccessRelation($resource, $relation);
    }
}

if (! function_exists('has_any_permission')) {
    /**
     * Check if the current user has any of the given permissions.
     */
    function has_any_permission(array $permissions): bool
    {
        return app(PermissionService::class)->hasAnyPermission($permissions);
    }
}

if (! function_exists('has_all_permissions')) {
    /**
     * Check if the current user has all of the given permissions.
     */
    function has_all_permissions(array $permissions): bool
    {
        return app(PermissionService::class)->hasAllPermissions($permissions);
    }
}

