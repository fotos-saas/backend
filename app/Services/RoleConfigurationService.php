<?php

namespace App\Services;

use App\Models\NavigationConfiguration;
use App\Models\NavigationGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Service for importing and exporting role configurations.
 *
 * Handles export/import of permissions and navigation settings
 * in JSON format for easy backup and migration between environments.
 */
class RoleConfigurationService
{
    /**
     * Export role configuration including permissions and navigation.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return array
     */
    public function exportRoleConfiguration(Role $role): array
    {
        // Get all permissions for this role
        $permissions = $role->permissions->pluck('name')->toArray();

        // Get navigation configurations
        $navigationItems = NavigationConfiguration::where('role_id', $role->id)
            ->get()
            ->map(function ($config) {
                return [
                    'resource_key' => $config->resource_key,
                    'label' => $config->label,
                    'navigation_group' => $config->navigation_group,
                    'sort_order' => $config->sort_order,
                    'is_visible' => $config->is_visible,
                ];
            })
            ->toArray();

        // Get navigation groups for this role
        $navigationGroups = NavigationGroup::where('role_id', $role->id)
            ->get()
            ->map(function ($group) {
                return [
                    'key' => $group->key,
                    'label' => $group->label,
                    'sort_order' => $group->sort_order,
                    'collapsed' => $group->collapsed,
                    'is_system' => $group->is_system,
                ];
            })
            ->toArray();

        return [
            'role' => $role->name,
            'exported_at' => now()->toIso8601String(),
            'permissions' => $permissions,
            'navigation' => [
                'items' => $navigationItems,
                'groups' => $navigationGroups,
            ],
        ];
    }

    /**
     * Export role configuration as JSON string.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  bool  $prettyPrint
     * @return string
     */
    public function exportRoleConfigurationAsJson(Role $role, bool $prettyPrint = true): string
    {
        $config = $this->exportRoleConfiguration($role);

        return json_encode(
            $config,
            $prettyPrint ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Import role configuration from array.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  array  $config
     * @param  bool  $mergeMode  If true, merge with existing config. If false, replace entirely.
     * @return array  Result with success status and messages
     */
    public function importRoleConfiguration(Role $role, array $config, bool $mergeMode = false): array
    {
        // Validate the configuration structure
        $validation = $this->validateConfiguration($config);
        if (! $validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        $messages = [];
        $errors = [];

        DB::beginTransaction();

        try {
            // Import permissions
            if (isset($config['permissions'])) {
                $permissionResult = $this->importPermissions($role, $config['permissions'], $mergeMode);
                $messages = array_merge($messages, $permissionResult['messages']);
                $errors = array_merge($errors, $permissionResult['errors']);
            }

            // Import navigation items
            if (isset($config['navigation']['items'])) {
                $navItemsResult = $this->importNavigationItems($role, $config['navigation']['items'], $mergeMode);
                $messages = array_merge($messages, $navItemsResult['messages']);
                $errors = array_merge($errors, $navItemsResult['errors']);
            }

            // Import navigation groups
            if (isset($config['navigation']['groups'])) {
                $navGroupsResult = $this->importNavigationGroups($role, $config['navigation']['groups'], $mergeMode);
                $messages = array_merge($messages, $navGroupsResult['messages']);
                $errors = array_merge($errors, $navGroupsResult['errors']);
            }

            if (! empty($errors)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors' => $errors,
                    'messages' => $messages,
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'messages' => $messages,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors' => ['Hiba történt az importálás során: '.$e->getMessage()],
                'messages' => $messages,
            ];
        }
    }

    /**
     * Import role configuration from JSON string.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  string  $json
     * @param  bool  $mergeMode
     * @return array
     */
    public function importRoleConfigurationFromJson(Role $role, string $json, bool $mergeMode = false): array
    {
        try {
            $config = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'errors' => ['Érvénytelen JSON formátum: '.json_last_error_msg()],
                ];
            }

            return $this->importRoleConfiguration($role, $config, $mergeMode);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['JSON feldolgozási hiba: '.$e->getMessage()],
            ];
        }
    }

    /**
     * Validate configuration structure.
     *
     * @param  array  $config
     * @return array
     */
    protected function validateConfiguration(array $config): array
    {
        $errors = [];

        // Check required fields
        if (! isset($config['permissions']) && ! isset($config['navigation'])) {
            $errors[] = 'A konfigurációnak tartalmaznia kell legalább permissions vagy navigation mezőt.';
        }

        // Validate permissions structure
        if (isset($config['permissions']) && ! is_array($config['permissions'])) {
            $errors[] = 'A permissions mezőnek tömbnek kell lennie.';
        }

        // Validate navigation structure
        if (isset($config['navigation'])) {
            if (! is_array($config['navigation'])) {
                $errors[] = 'A navigation mezőnek tömbnek kell lennie.';
            } elseif (isset($config['navigation']['items']) && ! is_array($config['navigation']['items'])) {
                $errors[] = 'A navigation.items mezőnek tömbnek kell lennie.';
            } elseif (isset($config['navigation']['groups']) && ! is_array($config['navigation']['groups'])) {
                $errors[] = 'A navigation.groups mezőnek tömbnek kell lennie.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Import permissions for a role.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  array  $permissionNames
     * @param  bool  $mergeMode
     * @return array
     */
    protected function importPermissions(Role $role, array $permissionNames, bool $mergeMode): array
    {
        $messages = [];
        $errors = [];
        $added = 0;
        $skipped = 0;
        $removed = 0;

        // If not merge mode, remove all existing permissions
        if (! $mergeMode) {
            $existingCount = $role->permissions()->count();
            $role->syncPermissions([]);
            $removed = $existingCount;
        }

        // Add new permissions
        foreach ($permissionNames as $permissionName) {
            try {
                // Check if permission exists
                $permission = Permission::where('name', $permissionName)->first();

                if (! $permission) {
                    $errors[] = "Jogosultság nem létezik: {$permissionName}";
                    $skipped++;
                    continue;
                }

                // Check if role already has this permission
                if ($role->hasPermissionTo($permissionName)) {
                    if ($mergeMode) {
                        $skipped++;
                        continue;
                    }
                }

                $role->givePermissionTo($permission);
                $added++;
            } catch (\Exception $e) {
                $errors[] = "Hiba a jogosultság hozzáadásakor ({$permissionName}): ".$e->getMessage();
                $skipped++;
            }
        }

        if ($added > 0) {
            $messages[] = "{$added} jogosultság sikeresen hozzáadva.";
        }
        if ($removed > 0 && ! $mergeMode) {
            $messages[] = "{$removed} meglévő jogosultság eltávolítva.";
        }
        if ($skipped > 0) {
            $messages[] = "{$skipped} jogosultság kihagyva.";
        }

        return [
            'messages' => $messages,
            'errors' => $errors,
        ];
    }

    /**
     * Import navigation items for a role.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  array  $items
     * @param  bool  $mergeMode
     * @return array
     */
    protected function importNavigationItems(Role $role, array $items, bool $mergeMode): array
    {
        $messages = [];
        $errors = [];
        $added = 0;
        $updated = 0;
        $removed = 0;

        // If not merge mode, remove all existing navigation items
        if (! $mergeMode) {
            $existingCount = NavigationConfiguration::where('role_id', $role->id)->count();
            NavigationConfiguration::where('role_id', $role->id)->delete();
            $removed = $existingCount;
        }

        // Add/update navigation items
        foreach ($items as $item) {
            try {
                // Validate item structure
                if (! isset($item['resource_key'])) {
                    $errors[] = 'Hiányzó resource_key egy navigációs elemnél.';
                    continue;
                }

                $existing = NavigationConfiguration::where('role_id', $role->id)
                    ->where('resource_key', $item['resource_key'])
                    ->first();

                NavigationConfiguration::updateOrCreate(
                    [
                        'role_id' => $role->id,
                        'resource_key' => $item['resource_key'],
                    ],
                    [
                        'label' => $item['label'] ?? null,
                        'navigation_group' => $item['navigation_group'] ?? null,
                        'sort_order' => $item['sort_order'] ?? 0,
                        'is_visible' => $item['is_visible'] ?? true,
                    ]
                );

                if ($existing) {
                    $updated++;
                } else {
                    $added++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Hiba a navigációs elem importálásakor: '.$e->getMessage();
            }
        }

        if ($added > 0) {
            $messages[] = "{$added} navigációs elem hozzáadva.";
        }
        if ($updated > 0) {
            $messages[] = "{$updated} navigációs elem frissítve.";
        }
        if ($removed > 0 && ! $mergeMode) {
            $messages[] = "{$removed} meglévő navigációs elem eltávolítva.";
        }

        return [
            'messages' => $messages,
            'errors' => $errors,
        ];
    }

    /**
     * Import navigation groups for a role.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @param  array  $groups
     * @param  bool  $mergeMode
     * @return array
     */
    protected function importNavigationGroups(Role $role, array $groups, bool $mergeMode): array
    {
        $messages = [];
        $errors = [];
        $added = 0;
        $updated = 0;
        $removed = 0;

        // If not merge mode, remove all existing navigation groups (except system groups)
        if (! $mergeMode) {
            $existingCount = NavigationGroup::where('role_id', $role->id)
                ->where('is_system', false)
                ->count();
            NavigationGroup::where('role_id', $role->id)
                ->where('is_system', false)
                ->delete();
            $removed = $existingCount;
        }

        // Add/update navigation groups
        foreach ($groups as $group) {
            try {
                // Validate group structure
                if (! isset($group['key'])) {
                    $errors[] = 'Hiányzó key egy navigációs csoportnál.';
                    continue;
                }

                $existing = NavigationGroup::where('role_id', $role->id)
                    ->where('key', $group['key'])
                    ->first();

                // Don't overwrite system groups unless explicitly allowed
                if ($existing && $existing->is_system && ! ($group['is_system'] ?? false)) {
                    $errors[] = "Rendszer csoport nem módosítható: {$group['key']}";
                    continue;
                }

                NavigationGroup::updateOrCreate(
                    [
                        'role_id' => $role->id,
                        'key' => $group['key'],
                    ],
                    [
                        'label' => $group['label'] ?? $group['key'],
                        'sort_order' => $group['sort_order'] ?? 50,
                        'collapsed' => $group['collapsed'] ?? false,
                        'is_system' => $group['is_system'] ?? false,
                    ]
                );

                if ($existing) {
                    $updated++;
                } else {
                    $added++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Hiba a navigációs csoport importálásakor: '.$e->getMessage();
            }
        }

        if ($added > 0) {
            $messages[] = "{$added} navigációs csoport hozzáadva.";
        }
        if ($updated > 0) {
            $messages[] = "{$updated} navigációs csoport frissítve.";
        }
        if ($removed > 0 && ! $mergeMode) {
            $messages[] = "{$removed} meglévő navigációs csoport eltávolítva.";
        }

        return [
            'messages' => $messages,
            'errors' => $errors,
        ];
    }
}
