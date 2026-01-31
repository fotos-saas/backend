<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Assign default permissions to roles from config presets.
 *
 * This command assigns permissions based on role_presets in config/filament-permissions.php.
 * Safe to run in production - uses syncPermissions (replaces existing).
 */
class PermissionsAssignDefaultsCommand extends Command
{
    protected $signature = 'permissions:assign-defaults {--dry-run : Preview changes without applying}';

    protected $description = 'Assign default permissions to roles from config presets';

    public function handle(): int
    {
        $this->info('🎭 Assigning default permissions to roles...');

        $dryRun = $this->option('dry-run');
        $rolePresets = config('filament-permissions.role_presets', []);

        foreach ($rolePresets as $roleName => $permissionPatterns) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web']
            );

            $this->line("");
            $this->info("Processing: {$roleName}");

            if (empty($permissionPatterns)) {
                $this->comment("  ⊘ No permissions to assign");
                continue;
            }

            // Handle wildcard: assign ALL permissions
            if (in_array('*', $permissionPatterns)) {
                $allPermissions = Permission::all();

                if ($dryRun) {
                    $this->comment("  ✓ Would assign ALL permissions ({$allPermissions->count()})");
                } else {
                    $role->syncPermissions($allPermissions);
                    $this->info("  ✓ Assigned ALL permissions ({$allPermissions->count()})");
                }

                continue;
            }

            // Resolve wildcard permissions
            $resolvedPermissions = collect();

            foreach ($permissionPatterns as $permissionPattern) {
                if (str_ends_with($permissionPattern, '.*')) {
                    // Wildcard: find all matching permissions
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

            if ($dryRun) {
                $this->comment("  ✓ Would assign {$resolvedPermissions->count()} permission(s):");
                foreach ($resolvedPermissions as $perm) {
                    $this->comment("    - {$perm->name}");
                }
            } else {
                $role->syncPermissions($resolvedPermissions);
                $this->info("  ✓ Assigned {$resolvedPermissions->count()} permission(s)");
            }
        }

        $this->line("");

        if ($dryRun) {
            $this->info('✅ DRY-RUN completed! Run without --dry-run to apply changes.');
        } else {
            $this->info('✅ All role presets configured successfully!');
        }

        return 0;
    }
}
