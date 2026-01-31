<?php

namespace App\Services;

use App\Models\NavigationConfiguration;
use App\Models\NavigationGroup;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

/**
 * Service for managing role-specific navigation configurations.
 *
 * Handles auto-detection of Filament resources and applies
 * custom navigation settings based on user roles.
 */
class NavigationConfigService
{
    /**
     * Get navigation configuration for a specific role.
     *
     * Merges auto-detected resources with role-specific customizations.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return array<int, array<string, mixed>>
     */
    public function getNavigationForRole(Role $role): array
    {
        // Auto-detect all Filament resources
        $detectedResources = $this->autoDetectResources();

        // Get role-specific configurations
        $configs = NavigationConfiguration::where('role_id', $role->id)
            ->get()
            ->keyBy('resource_key');

        // Merge detected resources with custom configurations
        $navigation = [];
        foreach ($detectedResources as $resourceKey => $resource) {
            $config = $configs->get($resourceKey);

            // Check if role has permission to access this resource
            $hasPermission = $this->roleHasPermissionForResource($role, $resourceKey, $resource['class']);

            // Calculate actual visibility: config visibility AND has permission
            $configuredVisibility = $config?->is_visible ?? true;
            $actuallyVisible = $configuredVisibility && $hasPermission;

            $navigation[] = [
                'resource_key' => $resourceKey,
                'resource_class' => $resource['class'],
                'label' => $config?->label ?? $resource['default_label'],
                'group' => $config?->navigation_group ?? $resource['default_group'],
                'sort' => $config?->sort_order ?? $resource['default_sort'],
                'visible' => $actuallyVisible,
                'icon' => $resource['icon'],
                'url' => $resource['url'],
            ];
        }

        // Sort by sort_order and filter visible items
        return collect($navigation)
            ->where('visible', true)
            ->sortBy('sort')
            ->values()
            ->all();
    }

    /**
     * Auto-detect all Filament resources in the application.
     *
     * Scans the app/Filament/Resources directory and extracts
     * navigation information from each resource.
     *
     * @return array<string, array<string, mixed>>
     */
    public function autoDetectResources(): array
    {
        $resources = [];

        // Add Dashboard as a special case (it's a Page, not a Resource)
        $resources['dashboard'] = [
            'class' => 'App\\Filament\\Pages\\Dashboard',
            'default_label' => 'Vezérlőpult',
            'default_group' => null,
            'default_sort' => -1, // Always first
            'icon' => 'heroicon-o-home',
            'url' => '/admin',
        ];

        $resourcePath = app_path('Filament/Resources');

        if (! File::isDirectory($resourcePath)) {
            return $resources;
        }

        // Get all Resource files recursively
        $files = File::allFiles($resourcePath);

        foreach ($files as $file) {
            // Only process *Resource.php files, not Page files
            if (! str_ends_with($file->getFilename(), 'Resource.php')) {
                continue;
            }

            // Skip page files inside resource directories
            if (str_contains($file->getPath(), '/Pages')) {
                continue;
            }

            $class = $this->getClassFromFile($file->getPathname());

            if (! $class || ! class_exists($class)) {
                continue;
            }

            // Check if it's a valid Filament Resource
            if (! is_subclass_of($class, Resource::class)) {
                continue;
            }

            // Skip resources that have $shouldRegisterNavigation = false
            $reflection = new \ReflectionClass($class);
            $properties = $reflection->getStaticProperties();
            if (isset($properties['shouldRegisterNavigation']) && $properties['shouldRegisterNavigation'] === false) {
                continue;
            }

            $resourceKey = $this->getResourceKey($class);

            try {
                $resources[$resourceKey] = [
                    'class' => $class,
                    'default_label' => $class::getNavigationLabel() ?? class_basename($class),
                    'default_group' => $class::getNavigationGroup(),
                    'default_sort' => $class::getNavigationSort() ?? 0,
                    'icon' => $class::getNavigationIcon(),
                    'url' => $class::getUrl(),
                ];
            } catch (\Exception $e) {
                // Skip resources that can't be instantiated
                continue;
            }
        }

        return $resources;
    }

    /**
     * Get navigation groups for a specific role.
     *
     * Returns role-specific groups or default groups if none exist.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Support\Collection
     */
    public function getNavigationGroupsForRole(Role $role)
    {
        // First try to get role-specific groups
        $roleGroups = NavigationGroup::where('role_id', $role->id)
            ->orderBy('sort_order')
            ->get();

        // If no role-specific groups, use defaults (role_id = null)
        if ($roleGroups->isEmpty()) {
            $roleGroups = NavigationGroup::whereNull('role_id')
                ->orderBy('sort_order')
                ->get();
        }

        return $roleGroups->keyBy('key');
    }

    /**
     * Extract class namespace from file path.
     *
     * @param  string  $filePath
     * @return string|null
     */
    protected function getClassFromFile(string $filePath): ?string
    {
        $relativePath = str_replace(app_path().'/', '', $filePath);
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace('/', '\\', $relativePath);

        return 'App\\'.$relativePath;
    }

    /**
     * Generate resource key from class name.
     *
     * Converts class name to kebab-case key with smart pluralization.
     * Examples: WorkSessionResource -> work-sessions, QueueManagementResource -> queue-management
     *
     * @param  string  $class
     * @return string
     */
    protected function getResourceKey(string $class): string
    {
        $basename = class_basename($class);
        $key = str_replace('Resource', '', $basename);

        // Convert to kebab-case
        $key = \Illuminate\Support\Str::kebab($key);

        // Don't pluralize if it ends with 'management'
        if (str_ends_with($key, '-management')) {
            return $key;
        }

        // For 'setting' suffix, always pluralize to 'settings'
        if (str_ends_with($key, '-setting')) {
            return substr($key, 0, -7) . 'settings';
        }

        // Pluralize the key for all other cases
        $key = \Illuminate\Support\Str::plural($key);

        return $key;
    }

    /**
     * Initialize default navigation groups if they don't exist.
     *
     * @return void
     */
    public function initializeDefaultGroups(): void
    {
        $defaultGroups = [
            [
                'key' => 'platform-settings',
                'label' => 'Platform Beállítások',
                'sort_order' => 100,
                'is_system' => true,
                'collapsed' => true,
            ],
            [
                'key' => 'shipping-payment',
                'label' => 'Szállítás és Fizetés',
                'sort_order' => 50,
                'is_system' => true,
                'collapsed' => true,
            ],
            [
                'key' => 'email-system',
                'label' => 'Email Rendszer',
                'sort_order' => 90,
                'is_system' => true,
                'collapsed' => true,
            ],
        ];

        foreach ($defaultGroups as $group) {
            NavigationGroup::firstOrCreate(
                ['key' => $group['key'], 'role_id' => null],
                $group
            );
        }
    }

    /**
     * Check if a role has permission to access a given resource.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  string  $resourceKey
     * @param  string  $resourceClass
     * @return bool
     */
    protected function roleHasPermissionForResource(Role $role, string $resourceKey, string $resourceClass): bool
    {
        // Special case: Dashboard is always visible if user has dashboard.view permission
        if ($resourceKey === 'dashboard' || str_contains($resourceClass, 'Pages\\Dashboard')) {
            try {
                return $role->hasPermissionTo('dashboard.view');
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                return false;
            }
        }

        // Check if the resource requires permissions
        // The permission key format is: {resource_key}.view
        $permissionName = "{$resourceKey}.view";

        // Check if the permission exists
        try {
            return $role->hasPermissionTo($permissionName);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // If permission doesn't exist, default to false (deny access)
            return false;
        }
    }
}
