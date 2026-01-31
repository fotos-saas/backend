<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasGranularPermissions;
use Filament\Resources\Resource;

/**
 * Base Resource class for all Filament Resources with automatic permission handling.
 *
 * This class automatically includes the HasGranularPermissions trait,
 * eliminating the need to manually add it to every Resource.
 *
 * Usage:
 * - Extend BaseResource instead of Resource
 * - No need to add "use HasGranularPermissions;" manually
 *
 * Example:
 * class TabloStatusResource extends BaseResource { ... }
 *
 * Security:
 * - All CRUD operations are protected by permission checks
 * - New Resources are "closed by default" (no access without explicit permissions)
 * - Super Admin role always has full access
 *
 * @see \App\Filament\Concerns\HasGranularPermissions
 * @see \App\Services\PermissionService
 */
abstract class BaseResource extends Resource
{
    use HasGranularPermissions;

    // All permission methods are defined in HasGranularPermissions trait:
    // - shouldRegisterNavigation()
    // - canViewAny()
    // - canView($record)
    // - canCreate()
    // - canEdit($record)
    // - canDelete($record)
    // - canDeleteAny()
    // - getPermissionKey()
    // - canAccessTab($tabName)
    // - canSeeField($fieldName)
    // - canAccessAction($actionName)
    // - canAccessRelation($relationName)
}
