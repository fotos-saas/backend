<?php

namespace App\Console\Commands;

use App\Services\ResourceDiscoveryService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * Synchronize permissions from config file and auto-discovered Resources.
 *
 * This command ensures permissions match:
 * 1. Auto-discovered Filament Resources (if enabled)
 * 2. Manual config/filament-permissions.php overrides
 *
 * Safe to run in production - only creates missing permissions, doesn't delete.
 *
 * Security: New Resources are "closed by default" - permissions exist but no role has them.
 */
class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync
        {--dry-run : Preview changes without applying}
        {--no-discover : Disable auto-discovery, use only config file}
        {--list : List all discovered resources without syncing}
        {--no-assign : Do not auto-assign permissions to roles}';

    protected $description = 'Sync permissions from auto-discovered Resources and config file';

    public function handle(ResourceDiscoveryService $discoveryService): int
    {
        // List mode - just show discovered resources
        if ($this->option('list')) {
            return $this->listResources($discoveryService);
        }

        $this->info('🔄 Synchronizing permissions...');

        $dryRun = $this->option('dry-run');
        $noDiscover = $this->option('no-discover');
        $autoDiscoverEnabled = config('filament-permissions.auto_discover', true);

        // Get resources from config and/or auto-discovery
        $resources = $this->getResources($discoveryService, $noDiscover, $autoDiscoverEnabled);

        $permissionsCreated = 0;
        $resourcesProcessed = 0;

        foreach ($resources as $resourceKey => $resource) {
            $label = $resource['label'] ?? $resourceKey;
            $isAutoDiscovered = $resource['auto_discovered'] ?? false;
            $marker = $isAutoDiscovered ? '🔍' : '📁';

            $this->line("{$marker} Processing: {$label} ({$resourceKey})");
            $resourcesProcessed++;

            // Sync basic permissions
            if (isset($resource['permissions'])) {
                foreach ($resource['permissions'] as $permKey => $permLabel) {
                    $permissionName = "{$resourceKey}.{$permKey}";
                    if ($this->syncPermission($permissionName, $dryRun)) {
                        $permissionsCreated++;
                    }
                }
            }

            // Sync tab permissions
            if (isset($resource['tabs'])) {
                foreach (array_keys($resource['tabs']) as $tabKey) {
                    $permissionName = "{$resourceKey}.tab.{$tabKey}";
                    if ($this->syncPermission($permissionName, $dryRun)) {
                        $permissionsCreated++;
                    }
                }
            }

            // Sync action permissions
            if (isset($resource['actions'])) {
                foreach (array_keys($resource['actions']) as $actionKey) {
                    $permissionName = "{$resourceKey}.action.{$actionKey}";
                    if ($this->syncPermission($permissionName, $dryRun)) {
                        $permissionsCreated++;
                    }
                }
            }

            // Sync relation permissions
            if (isset($resource['relations'])) {
                foreach (array_keys($resource['relations']) as $relationKey) {
                    $permissionName = "{$resourceKey}.relation.{$relationKey}";
                    if ($this->syncPermission($permissionName, $dryRun)) {
                        $permissionsCreated++;
                    }
                }
            }

            // Sync wildcard
            if ($this->syncPermission("{$resourceKey}.*", $dryRun)) {
                $permissionsCreated++;
            }
        }

        // Sync global wildcard
        if ($this->syncPermission('*', $dryRun)) {
            $permissionsCreated++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("✅ DRY-RUN: {$resourcesProcessed} resource(s) found, {$permissionsCreated} permission(s) would be created");

            return 0;
        }

        $this->info("✅ {$resourcesProcessed} resource(s) processed, {$permissionsCreated} new permission(s) created!");

        // Auto-assign permissions to roles if new permissions were created
        if ($permissionsCreated > 0 && ! $this->option('no-assign')) {
            $this->newLine();
            $this->info('🎭 Auto-assigning permissions to roles (from config presets)...');
            $this->call('permissions:assign-defaults');
        } elseif ($permissionsCreated > 0) {
            $this->newLine();
            $this->warn('⚠️  SECURITY: New permissions are "closed by default".');
            $this->warn('   Run `php artisan permissions:assign-defaults` to assign them to roles.');
        }

        return 0;
    }

    /**
     * Get resources from config and/or auto-discovery.
     */
    protected function getResources(
        ResourceDiscoveryService $discoveryService,
        bool $noDiscover,
        bool $autoDiscoverEnabled
    ): array {
        $configResources = config('filament-permissions.resources', []);
        $configOverrides = config('filament-permissions.resource_overrides', []);

        // If auto-discover is disabled, use only config
        if ($noDiscover || ! $autoDiscoverEnabled) {
            $this->comment('📋 Using config file only (auto-discovery disabled)');

            return $configResources;
        }

        // Auto-discover resources
        $this->comment('🔍 Auto-discovering Resources...');
        $discovered = $discoveryService->discoverResources();
        $count = count($discovered);
        $this->comment("   Found {$count} Resource(s)");

        // Merge: discovered + config resources + config overrides
        // Priority: config overrides > config resources > discovered
        $merged = $discoveryService->mergeWithOverrides($discovered, $configResources);
        $merged = $discoveryService->mergeWithOverrides($merged, $configOverrides);

        return $merged;
    }

    /**
     * List all discovered resources.
     */
    protected function listResources(ResourceDiscoveryService $discoveryService): int
    {
        $this->info('📋 Discovered Filament Resources:');
        $this->newLine();

        $resources = $discoveryService->discoverResources();

        if (empty($resources)) {
            $this->warn('No Resources found.');

            return 0;
        }

        $rows = [];
        foreach ($resources as $key => $resource) {
            $rows[] = [
                $key,
                $resource['label'],
                class_basename($resource['class']),
            ];
        }

        $this->table(['Permission Key', 'Label', 'Class'], $rows);
        $this->newLine();
        $this->info('Total: '.count($resources).' Resource(s)');

        return 0;
    }

    /**
     * Create a permission if it doesn't exist.
     */
    protected function syncPermission(string $name, bool $dryRun = false): bool
    {
        $exists = Permission::where('name', $name)->exists();

        if ($exists) {
            return false;
        }

        if (! $dryRun) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        $this->comment('  ✓ '.$name.($dryRun ? ' (would be created)' : ''));

        return true;
    }
}
