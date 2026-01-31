<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Generate all permissions from the config file.
 *
 * This seeder reads the filament-permissions.php config file
 * and creates all defined permissions in the database.
 */
class GeneratePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔧 Generating permissions from config...');

        $resources = config('filament-permissions.resources', []);
        $permissionsCreated = 0;

        foreach ($resources as $resourceKey => $resource) {
            $this->command->info("📁 Processing resource: {$resource['label']} ({$resourceKey})");

            // Create basic CRUD permissions
            if (isset($resource['permissions'])) {
                foreach ($resource['permissions'] as $permKey => $permLabel) {
                    $permissionName = "{$resourceKey}.{$permKey}";
                    $this->createPermission($permissionName, "{$resource['label']}: {$permLabel}");
                    $permissionsCreated++;
                }
            }

            // Create tab permissions
            if (isset($resource['tabs'])) {
                foreach ($resource['tabs'] as $tabKey => $tabLabel) {
                    $permissionName = "{$resourceKey}.tab.{$tabKey}";
                    $this->createPermission($permissionName, "{$resource['label']}: {$tabLabel} Tab");
                    $permissionsCreated++;
                }
            }

            // Create action permissions
            if (isset($resource['actions'])) {
                foreach ($resource['actions'] as $actionKey => $actionLabel) {
                    $permissionName = "{$resourceKey}.action.{$actionKey}";
                    $this->createPermission($permissionName, "{$resource['label']}: {$actionLabel}");
                    $permissionsCreated++;
                }
            }

            // Create relation manager permissions
            if (isset($resource['relations'])) {
                foreach ($resource['relations'] as $relationKey => $relationLabel) {
                    $permissionName = "{$resourceKey}.relation.{$relationKey}";
                    $this->createPermission($permissionName, "{$resource['label']}: {$relationLabel} Kapcsolat");
                    $permissionsCreated++;
                }
            }

            // Create wildcard permission for resource
            $wildcardPermission = "{$resourceKey}.*";
            $this->createPermission($wildcardPermission, "{$resource['label']}: Minden");
            $permissionsCreated++;
        }

        // Create global wildcard permission
        $this->createPermission('*', 'Összes Jogosultság');
        $permissionsCreated++;

        $this->command->info("✅ {$permissionsCreated} permission created/updated successfully!");

        // Assign role presets
        $this->assignRolePresets();
    }

    /**
     * Create or update a permission.
     */
    protected function createPermission(string $name, string $description = ''): void
    {
        Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['guard_name' => 'web']
        );

        $this->command->comment("  ✓ {$name}");
    }

    /**
     * Assign role presets from config.
     */
    protected function assignRolePresets(): void
    {
        $this->command->info('');
        $this->command->info('🎭 Assigning role presets...');

        $rolePresets = config('filament-permissions.role_presets', []);

        foreach ($rolePresets as $roleName => $permissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web']
            );

            if (empty($permissions)) {
                $this->command->comment("  ⊘ {$roleName}: No permissions");

                continue;
            }

            // Handle wildcard: assign ALL permissions
            if (in_array('*', $permissions)) {
                $allPermissions = Permission::all();
                $role->syncPermissions($allPermissions);
                $this->command->info("  ✓ {$roleName}: ALL permissions ({$allPermissions->count()})");

                continue;
            }

            // Resolve wildcard permissions (e.g., work-sessions.*)
            $resolvedPermissions = collect();

            foreach ($permissions as $permissionPattern) {
                if (str_ends_with($permissionPattern, '.*')) {
                    // Wildcard permission: find all matching permissions
                    $prefix = str_replace('.*', '', $permissionPattern);
                    $matching = Permission::where('name', 'like', $prefix.'.%')
                        ->orWhere('name', $permissionPattern)
                        ->get();
                    $resolvedPermissions = $resolvedPermissions->merge($matching);
                } else {
                    // Exact permission
                    $perm = Permission::where('name', $permissionPattern)->first();
                    if ($perm) {
                        $resolvedPermissions->push($perm);
                    }
                }
            }

            $resolvedPermissions = $resolvedPermissions->unique('id');
            $role->syncPermissions($resolvedPermissions);

            $this->command->info("  ✓ {$roleName}: {$resolvedPermissions->count()} permissions");
        }

        $this->command->info('');
        $this->command->info('🎉 All permissions and role presets configured successfully!');
    }
}

