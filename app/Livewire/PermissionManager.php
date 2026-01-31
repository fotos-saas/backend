<?php

namespace App\Livewire;

use App\Services\NavigationConfigService;
use App\Services\PermissionService;
use App\Services\RoleConfigurationService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Livewire component for managing role permissions with a hierarchical UI.
 *
 * This component provides a user-friendly interface for assigning
 * granular permissions to roles with expandable resource groups.
 */
class PermissionManager extends Component
{
    use WithFileUploads;

    public ?int $selectedRoleId = null;

    public ?Role $selectedRole = null;

    public array $expandedGroups = [];

    public array $rolePermissions = [];

    // Import/Export properties
    public $importFile = null;

    public bool $showImportModal = false;

    public bool $importMergeMode = false;

    public array $importResult = [];

    public function mount(?int $roleId = null): void
    {
        if ($roleId) {
            $this->selectRole($roleId);
        }
    }

    public function selectRole(int $roleId): void
    {
        $this->selectedRoleId = $roleId;
        $this->selectedRole = Role::find($roleId);

        if ($this->selectedRole) {
            $this->rolePermissions = $this->selectedRole
                ->permissions
                ->pluck('name')
                ->toArray();
        } else {
            $this->rolePermissions = [];
        }
    }

    public function toggleExpanded(string $resourceKey): void
    {
        if (in_array($resourceKey, $this->expandedGroups)) {
            $this->expandedGroups = array_diff($this->expandedGroups, [$resourceKey]);
        } else {
            $this->expandedGroups[] = $resourceKey;
        }
    }

    public function expandAll(): void
    {
        $resources = config('filament-permissions.resources', []);
        $this->expandedGroups = array_keys($resources);
    }

    public function collapseAll(): void
    {
        $this->expandedGroups = [];
    }

    public function togglePermission(string $permissionName): void
    {
        if (! $this->selectedRole) {
            return;
        }

        $permission = Permission::where('name', $permissionName)->first();

        if (! $permission) {
            return;
        }

        if ($this->selectedRole->hasPermissionTo($permissionName)) {
            $this->selectedRole->revokePermissionTo($permissionName);
            $this->rolePermissions = array_diff($this->rolePermissions, [$permissionName]);
        } else {
            $this->selectedRole->givePermissionTo($permissionName);
            $this->rolePermissions[] = $permissionName;
        }

        // Clear permission cache for all users with this role
        app(PermissionService::class)->refreshRolePermissions($this->selectedRole);

        // Refresh to update UI
        $this->selectedRole = $this->selectedRole->fresh();
    }

    public function toggleResourceAll(string $resourceKey): void
    {
        if (! $this->selectedRole) {
            return;
        }

        $resourceConfig = config("filament-permissions.resources.{$resourceKey}");
        $allPermissions = $this->getAllPermissionsForResource($resourceKey, $resourceConfig);

        $hasAll = true;
        foreach ($allPermissions as $permName) {
            if (! in_array($permName, $this->rolePermissions)) {
                $hasAll = false;
                break;
            }
        }

        if ($hasAll) {
            // Remove all permissions
            foreach ($allPermissions as $permName) {
                $permission = Permission::where('name', $permName)->first();
                if ($permission) {
                    $this->selectedRole->revokePermissionTo($permission);
                }
            }
            $this->rolePermissions = array_diff($this->rolePermissions, $allPermissions);
        } else {
            // Add all permissions
            foreach ($allPermissions as $permName) {
                $permission = Permission::where('name', $permName)->first();
                if ($permission && ! $this->selectedRole->hasPermissionTo($permName)) {
                    $this->selectedRole->givePermissionTo($permission);
                    if (! in_array($permName, $this->rolePermissions)) {
                        $this->rolePermissions[] = $permName;
                    }
                }
            }
        }

        // Clear permission cache for all users with this role
        app(PermissionService::class)->refreshRolePermissions($this->selectedRole);

        $this->selectedRole = $this->selectedRole->fresh();
    }

    public function hasPermission(string $permissionName): bool
    {
        return in_array($permissionName, $this->rolePermissions);
    }

    public function resourceHasAllPermissions(string $resourceKey): bool
    {
        $resourceConfig = config("filament-permissions.resources.{$resourceKey}");
        $allPermissions = $this->getAllPermissionsForResource($resourceKey, $resourceConfig);

        foreach ($allPermissions as $permName) {
            if (! in_array($permName, $this->rolePermissions)) {
                return false;
            }
        }

        return true;
    }

    protected function getAllPermissionsForResource(string $resourceKey, array $resourceConfig): array
    {
        $permissions = [];

        // Basic permissions
        if (isset($resourceConfig['permissions'])) {
            foreach (array_keys($resourceConfig['permissions']) as $permKey) {
                $permissions[] = "{$resourceKey}.{$permKey}";
            }
        }

        // Tab permissions
        if (isset($resourceConfig['tabs'])) {
            foreach (array_keys($resourceConfig['tabs']) as $tabKey) {
                $permissions[] = "{$resourceKey}.tab.{$tabKey}";
            }
        }

        // Action permissions
        if (isset($resourceConfig['actions'])) {
            foreach (array_keys($resourceConfig['actions']) as $actionKey) {
                $permissions[] = "{$resourceKey}.action.{$actionKey}";
            }
        }

        // Relation permissions
        if (isset($resourceConfig['relations'])) {
            foreach (array_keys($resourceConfig['relations']) as $relationKey) {
                $permissions[] = "{$resourceKey}.relation.{$relationKey}";
            }
        }

        return $permissions;
    }

    public function render()
    {
        $roles = Role::all();
        $resourcesConfig = config('filament-permissions.resources', []);

        // Get detected resources with actual namespaces
        $navService = app(NavigationConfigService::class);
        $detectedResources = $navService->autoDetectResources();

        // Enrich resources with namespace information
        $resources = [];
        foreach ($resourcesConfig as $key => $config) {
            $resources[$key] = $config;

            // Priority: 1) Config namespace, 2) Detected resource, 3) Fallback
            if (isset($config['namespace'])) {
                $resources[$key]['namespace'] = $config['namespace'];
            } elseif (isset($detectedResources[$key])) {
                $resources[$key]['namespace'] = $detectedResources[$key]['class'];
            } else {
                $resources[$key]['namespace'] = 'N/A';
            }

            $resources[$key]['is_visible'] = $this->isResourceVisible($key);
        }

        // Sort resources: visible ones first, then alphabetically by label
        uasort($resources, function ($a, $b) {
            // First, sort by visibility (visible first)
            if ($a['is_visible'] !== $b['is_visible']) {
                return $b['is_visible'] <=> $a['is_visible']; // true > false
            }
            // Then sort alphabetically by label
            return strcasecmp($a['label'] ?? '', $b['label'] ?? '');
        });

        return view('livewire.permission-manager', [
            'roles' => $roles,
            'resources' => $resources,
        ]);
    }

    /**
     * Check if the resource should be visible in navigation
     */
    public function isResourceVisible(string $resourceKey): bool
    {
        // Check if the resource should be visible in navigation
        // based on permissions and role settings
        return $this->hasPermission("{$resourceKey}.view");
    }

    /**
     * Export role configuration as JSON file download.
     */
    public function exportConfiguration(): StreamedResponse
    {
        if (! $this->selectedRole) {
            session()->flash('error', 'Kérlek válassz ki egy szerepkört az exportáláshoz.');

            return response()->streamDownload(function () {
            }, 'error.txt');
        }

        $service = app(RoleConfigurationService::class);
        $json = $service->exportRoleConfigurationAsJson($this->selectedRole);

        $fileName = 'role_config_'.$this->selectedRole->name.'_'.now()->format('Y-m-d_His').'.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $fileName, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Open import modal.
     */
    public function openImportModal(): void
    {
        if (! $this->selectedRole) {
            session()->flash('error', 'Kérlek válassz ki egy szerepkört az importáláshoz.');

            return;
        }

        $this->showImportModal = true;
        $this->importFile = null;
        $this->importMergeMode = false;
        $this->importResult = [];
    }

    /**
     * Close import modal.
     */
    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importResult = [];
    }

    /**
     * Import role configuration from uploaded JSON file.
     */
    public function importConfiguration(): void
    {
        if (! $this->selectedRole) {
            $this->importResult = [
                'success' => false,
                'errors' => ['Nincs kiválasztva szerepkör.'],
            ];

            return;
        }

        $this->validate([
            'importFile' => 'required|file|mimes:json,txt|max:2048',
        ], [
            'importFile.required' => 'Kérlek tölts fel egy JSON fájlt.',
            'importFile.mimes' => 'Csak JSON fájlok engedélyezettek.',
            'importFile.max' => 'A fájl mérete maximum 2MB lehet.',
        ]);

        try {
            $json = file_get_contents($this->importFile->getRealPath());
            $service = app(RoleConfigurationService::class);

            $result = $service->importRoleConfigurationFromJson(
                $this->selectedRole,
                $json,
                $this->importMergeMode
            );

            $this->importResult = $result;

            if ($result['success']) {
                // Reload role data
                $this->selectRole($this->selectedRoleId);

                session()->flash('import-success', 'Konfiguráció sikeresen importálva!');

                // Close modal after 2 seconds
                $this->dispatch('import-complete');
            }
        } catch (\Exception $e) {
            $this->importResult = [
                'success' => false,
                'errors' => ['Hiba történt az importálás során: '.$e->getMessage()],
            ];
        }
    }
}

