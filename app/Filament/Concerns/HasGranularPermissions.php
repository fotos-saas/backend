<?php

namespace App\Filament\Concerns;

use App\Services\PermissionService;
use Illuminate\Support\Str;

/**
 * Trait for adding granular permission checks to Filament Resources.
 *
 * This trait provides methods to check permissions for navigation,
 * CRUD operations, tabs, fields, actions, and relation managers.
 */
trait HasGranularPermissions
{
    /**
     * Determine if the resource should be registered in the navigation.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.view');
    }

    /**
     * Determine if the user can view any records.
     */
    public static function canViewAny(): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.view');
    }

    /**
     * Determine if the user can view a specific record.
     */
    public static function canView($record): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.view');
    }

    /**
     * Determine if the user can create a new record.
     */
    public static function canCreate(): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.create');
    }

    /**
     * Determine if the user can edit a record.
     */
    public static function canEdit($record): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.edit');
    }

    /**
     * Determine if the user can delete a record.
     */
    public static function canDelete($record): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.delete');
    }

    /**
     * Determine if the user can delete any records in bulk.
     */
    public static function canDeleteAny(): bool
    {
        return app(PermissionService::class)->hasPermission(static::getPermissionKey().'.delete');
    }

    /**
     * Get the permission key for this resource.
     *
     * This method tries to match the resource to a config key in the following order:
     * 1. Check if a config key exists matching the class name in kebab-case
     * 2. Check if a config key exists matching the namespace (for nested resources)
     * 3. Fall back to plural model label slug
     *
     * Examples:
     * - App\Filament\Resources\PhotoResource -> "photos" (from config)
     * - App\Filament\Resources\UserResource -> "users" (from config)
     * - App\Filament\Resources\WorkSessions\WorkSessionResource -> "work-sessions" (from namespace)
     */
    protected static function getPermissionKey(): string
    {
        $className = class_basename(static::class);
        $resourceName = str_replace('Resource', '', $className);
        $slug = Str::slug(Str::kebab($resourceName));

        // Get all available config keys
        $configKeys = array_keys(config('filament-permissions.resources', []));

        // Strategy 1: Try exact match with pluralized class name
        $pluralSlug = Str::plural($slug);
        if (in_array($pluralSlug, $configKeys)) {
            return $pluralSlug;
        }

        // Strategy 2: Try singular class name
        if (in_array($slug, $configKeys)) {
            return $slug;
        }

        // Strategy 3: Try to match by namespace (for nested resources like WorkSessions\WorkSessionResource)
        $fullClassName = static::class;
        $namespaceParts = explode('\\', $fullClassName);

        // Check if resource is in a subdirectory (e.g., WorkSessions\WorkSessionResource)
        if (count($namespaceParts) > 4) { // App\Filament\Resources\[Subdirectory]\[Resource]
            $subdirectory = $namespaceParts[3];
            $kebabSubdir = Str::slug(Str::kebab($subdirectory));
            if (in_array($kebabSubdir, $configKeys)) {
                return $kebabSubdir;
            }
        }

        // Strategy 4: Fall back to plural model label slug (last resort)
        return Str::slug(static::getPluralModelLabel());
    }

    /**
     * Check if a specific tab should be visible.
     */
    public static function canAccessTab(string $tabName): bool
    {
        return app(PermissionService::class)->canAccessTab(static::getPermissionKey(), $tabName);
    }

    /**
     * Check if a specific field should be visible.
     */
    public static function canSeeField(string $fieldName): bool
    {
        return app(PermissionService::class)->canSeeField(static::getPermissionKey(), $fieldName);
    }

    /**
     * Check if a specific action should be accessible.
     */
    public static function canAccessAction(string $actionName): bool
    {
        return app(PermissionService::class)->canAccessAction(static::getPermissionKey(), $actionName);
    }

    /**
     * Check if a specific relation manager should be accessible.
     */
    public static function canAccessRelation(string $relationName): bool
    {
        return app(\App\Services\PermissionService::class)->canAccessRelation(static::getPermissionKey(), $relationName);
    }
}

