<?php

namespace App\Livewire;

use App\Models\NavigationConfiguration;
use App\Models\NavigationGroup;
use App\Services\NavigationConfigService;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Livewire component for managing role-specific navigation configurations.
 *
 * Provides an intuitive UI for customizing menu items per role including
 * labels, groups, sort order, and visibility with live preview.
 */
class NavigationManager extends Component
{
    public ?int $selectedRoleId = null;

    public ?Role $selectedRole = null;

    public array $navigationItems = [];

    public array $navigationGroups = [];

    public array $expandedItems = [];

    public string $search = '';

    public bool $showNewGroupModal = false;

    public string $newGroupKey = '';

    public string $newGroupLabel = '';

    public int $newGroupSortOrder = 50;

    public bool $showManageGroupsModal = false;

    public ?int $editingGroupId = null;

    public string $editGroupKey = '';

    public string $editGroupLabel = '';

    public int $editGroupSortOrder = 50;

    protected NavigationConfigService $navService;

    public function boot(NavigationConfigService $navService): void
    {
        $this->navService = $navService;
    }

    public function mount(?int $roleId = null): void
    {
        if ($roleId) {
            $this->selectRole($roleId);
        }
    }

    /**
     * Select a role and load its navigation configuration.
     */
    public function selectRole(int $roleId): void
    {
        $this->selectedRoleId = $roleId;
        $this->selectedRole = Role::find($roleId);

        if ($this->selectedRole) {
            $this->loadNavigationItems();
            $this->loadNavigationGroups();
        } else {
            $this->navigationItems = [];
            $this->navigationGroups = [];
        }
    }

    /**
     * Load navigation items for the selected role.
     */
    protected function loadNavigationItems(): void
    {
        if (! $this->selectedRole) {
            $this->navigationItems = [];

            return;
        }

        // Get all detected resources
        $detectedResources = $this->navService->autoDetectResources();

        // Get existing configurations for this role
        $configs = NavigationConfiguration::where('role_id', $this->selectedRoleId)
            ->get()
            ->keyBy('resource_key');

        // Merge detected + configured
        $items = [];
        foreach ($detectedResources as $resourceKey => $resource) {
            $config = $configs->get($resourceKey);

            // Calculate actual visibility based on config AND permissions
            $configuredVisibility = $config?->is_visible ?? true;
            $hasPermission = $this->hasPermissionForResource($resource['class'], $this->selectedRole);
            $actuallyVisible = $configuredVisibility && $hasPermission;

            $items[] = [
                'resource_key' => $resourceKey,
                'resource_class' => $resource['class'],
                'default_label' => $resource['default_label'],
                'label' => $config?->label ?? $resource['default_label'],
                'group' => $config?->navigation_group ?? $resource['default_group'],
                'sort_order' => $config?->sort_order ?? $resource['default_sort'],
                'is_visible' => $actuallyVisible, // Use calculated visibility
                'configured_visibility' => $configuredVisibility, // Store configured value separately
                'has_permission' => $hasPermission, // Store permission check result
                'icon' => $resource['icon'],
                'is_configured' => $config !== null,
            ];
        }

        // Sort items: visible first, then by sort_order
        $this->navigationItems = collect($items)
            ->sortBy([
                ['is_visible', 'desc'],  // visible items first
                ['sort_order', 'asc'],   // then by sort order
            ])
            ->values()
            ->all();
    }

    /**
     * Load navigation groups for the selected role.
     */
    protected function loadNavigationGroups(): void
    {
        if (! $this->selectedRole) {
            $this->navigationGroups = [];

            return;
        }

        $groups = $this->navService->getNavigationGroupsForRole($this->selectedRole);

        // Convert to properly structured array with 'key' as array key
        $formattedGroups = [];
        foreach ($groups as $key => $group) {
            $formattedGroups[$key] = [
                'key' => $group->key,
                'label' => $group->label,
                'sort_order' => $group->sort_order,
                'is_system' => $group->is_system,
                'collapsed' => $group->collapsed,
            ];
        }
        $this->navigationGroups = $formattedGroups;
    }

    /**
     * Update navigation item label.
     */
    public function updateLabel(string $resourceKey, string $label): void
    {
        if (! $this->selectedRoleId) {
            return;
        }

        NavigationConfiguration::updateOrCreate(
            [
                'role_id' => $this->selectedRoleId,
                'resource_key' => $resourceKey,
            ],
            ['label' => $label ?: null]
        );

        $this->clearNavigationCache();
        $this->loadNavigationItems();
    }

    /**
     * Update navigation item group.
     */
    public function updateGroup(string $resourceKey, ?string $groupKey): void
    {
        if (! $this->selectedRoleId) {
            return;
        }

        NavigationConfiguration::updateOrCreate(
            [
                'role_id' => $this->selectedRoleId,
                'resource_key' => $resourceKey,
            ],
            ['navigation_group' => $groupKey]
        );

        $this->clearNavigationCache();
        $this->loadNavigationItems();
    }

    /**
     * Update navigation item sort order.
     */
    public function updateSortOrder(string $resourceKey, int $sortOrder): void
    {
        if (! $this->selectedRoleId) {
            return;
        }

        NavigationConfiguration::updateOrCreate(
            [
                'role_id' => $this->selectedRoleId,
                'resource_key' => $resourceKey,
            ],
            ['sort_order' => $sortOrder]
        );

        $this->clearNavigationCache();
        $this->loadNavigationItems();
    }

    /**
     * Toggle navigation item visibility.
     */
    public function toggleVisibility(string $resourceKey): void
    {
        if (! $this->selectedRoleId) {
            return;
        }

        $config = NavigationConfiguration::firstOrCreate(
            [
                'role_id' => $this->selectedRoleId,
                'resource_key' => $resourceKey,
            ]
        );

        $config->is_visible = ! $config->is_visible;
        $config->save();

        $this->clearNavigationCache();
        $this->loadNavigationItems();
    }

    /**
     * Reset navigation item to default.
     */
    public function resetToDefault(string $resourceKey): void
    {
        if (! $this->selectedRoleId) {
            return;
        }

        NavigationConfiguration::where('role_id', $this->selectedRoleId)
            ->where('resource_key', $resourceKey)
            ->delete();

        $this->loadNavigationItems();
    }

    /**
     * Toggle expanded state of navigation item card.
     */
    public function toggleExpanded(string $resourceKey): void
    {
        if (in_array($resourceKey, $this->expandedItems)) {
            $this->expandedItems = array_diff($this->expandedItems, [$resourceKey]);
        } else {
            $this->expandedItems[] = $resourceKey;
        }
    }

    /**
     * Expand all navigation item cards.
     */
    public function expandAll(): void
    {
        $this->expandedItems = array_column($this->navigationItems, 'resource_key');
    }

    /**
     * Collapse all navigation item cards.
     */
    public function collapseAll(): void
    {
        $this->expandedItems = [];
    }

    /**
     * Update menu order after drag-and-drop.
     */
    public function updateMenuOrder(array $orderedKeys): void
    {
        if (! $this->selectedRoleId) {
            return;
        }

        foreach ($orderedKeys as $index => $resourceKey) {
            NavigationConfiguration::updateOrCreate(
                [
                    'role_id' => $this->selectedRoleId,
                    'resource_key' => $resourceKey,
                ],
                ['sort_order' => $index]
            );
        }

        $this->loadNavigationItems();
    }

    /**
     * Open new group modal.
     */
    public function openNewGroupModal(): void
    {
        $this->showNewGroupModal = true;
        $this->newGroupKey = '';
        $this->newGroupLabel = '';
        $this->newGroupSortOrder = 50;
    }

    /**
     * Close new group modal.
     */
    public function closeNewGroupModal(): void
    {
        $this->showNewGroupModal = false;
        $this->newGroupKey = '';
        $this->newGroupLabel = '';
        $this->newGroupSortOrder = 50;
    }

    /**
     * Create new navigation group.
     */
    public function createNewGroup(): void
    {
        $this->validate([
            'newGroupKey' => 'required|string|max:255|regex:/^[a-z0-9\-]+$/',
            'newGroupLabel' => 'required|string|max:255',
            'newGroupSortOrder' => 'required|integer|min:0',
        ], [
            'newGroupKey.required' => 'A kulcs mező kötelező.',
            'newGroupKey.regex' => 'A kulcs csak kisbetűket, számokat és kötőjelet tartalmazhat.',
            'newGroupLabel.required' => 'A címke mező kötelező.',
            'newGroupSortOrder.required' => 'A sorrend mező kötelező.',
        ]);

        // Check if key already exists
        $exists = NavigationGroup::where('key', $this->newGroupKey)
            ->where(function ($query) {
                $query->where('role_id', $this->selectedRoleId)
                    ->orWhereNull('role_id');
            })
            ->exists();

        if ($exists) {
            $this->addError('newGroupKey', 'Ez a kulcs már létezik.');

            return;
        }

        NavigationGroup::create([
            'role_id' => $this->selectedRoleId,
            'key' => $this->newGroupKey,
            'label' => $this->newGroupLabel,
            'sort_order' => $this->newGroupSortOrder,
            'is_system' => false,
            'collapsed' => false,
        ]);

        $this->loadNavigationGroups();
        $this->closeNewGroupModal();

        // Show success notification
        session()->flash('group-created', 'Új csoport sikeresen létrehozva!');
    }

    /**
     * Open manage groups modal.
     */
    public function openManageGroupsModal(): void
    {
        $this->showManageGroupsModal = true;
        $this->loadNavigationGroups();
    }

    /**
     * Close manage groups modal.
     */
    public function closeManageGroupsModal(): void
    {
        $this->showManageGroupsModal = false;
        $this->editingGroupId = null;
        $this->editGroupKey = '';
        $this->editGroupLabel = '';
        $this->editGroupSortOrder = 50;
    }

    /**
     * Start editing a group.
     */
    public function editGroup(int $groupId): void
    {
        $group = NavigationGroup::find($groupId);

        if (!$group) {
            return;
        }

        $this->editingGroupId = $groupId;
        $this->editGroupKey = $group->key;
        $this->editGroupLabel = $group->label;
        $this->editGroupSortOrder = $group->sort_order;
    }

    /**
     * Save edited group.
     */
    public function saveEditedGroup(int $groupId): void
    {
        $group = NavigationGroup::find($groupId);

        if (!$group) {
            return;
        }

        $this->validate([
            'editGroupLabel' => 'required|string|max:255',
            'editGroupSortOrder' => 'required|integer|min:0',
        ], [
            'editGroupLabel.required' => 'A címke mező kötelező.',
            'editGroupSortOrder.required' => 'A sorrend mező kötelező.',
        ]);

        $group->update([
            'label' => $this->editGroupLabel,
            'sort_order' => $this->editGroupSortOrder,
        ]);

        $this->editingGroupId = null;
        $this->loadNavigationGroups();

        session()->flash('group-updated', 'Csoport sikeresen frissítve!');
    }

    /**
     * Cancel editing a group.
     */
    public function cancelEditGroup(): void
    {
        $this->editingGroupId = null;
        $this->editGroupKey = '';
        $this->editGroupLabel = '';
        $this->editGroupSortOrder = 50;
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(int $groupId): void
    {
        $group = NavigationGroup::find($groupId);

        if (!$group || $group->is_system) {
            session()->flash('group-error', 'Rendszer csoportot nem lehet törölni!');
            return;
        }

        // Update all navigation items that use this group to have no group
        NavigationConfiguration::where('role_id', $this->selectedRoleId)
            ->where('navigation_group', $group->key)
            ->update(['navigation_group' => null]);

        $group->delete();
        $this->loadNavigationGroups();
        $this->loadNavigationItems();

        session()->flash('group-deleted', 'Csoport sikeresen törölve!');
    }

    /**
     * Get filtered navigation items based on search.
     */
    public function getFilteredItemsProperty(): array
    {
        if (empty($this->search)) {
            return $this->navigationItems;
        }

        $searchLower = strtolower($this->search);

        return array_filter($this->navigationItems, function ($item) use ($searchLower) {
            return str_contains(strtolower($item['label']), $searchLower)
                || str_contains(strtolower($item['default_label']), $searchLower)
                || str_contains(strtolower($item['resource_key']), $searchLower);
        });
    }

    /**
     * Get preview navigation grouped by navigation groups.
     */
    public function getPreviewNavigationProperty(): array
    {
        $items = array_filter($this->navigationItems, fn ($item) => $item['is_visible']);

        $grouped = [];
        foreach ($items as $item) {
            $groupKey = $item['group'] ?? 'ungrouped';
            $grouped[$groupKey][] = $item;
        }

        return $grouped;
    }

    /**
     * Clear navigation cache to ensure changes are reflected immediately.
     */
    protected function clearNavigationCache(): void
    {
        // Clear Laravel cache
        \Cache::flush();

        // Clear Filament view cache
        \Artisan::call('view:clear');

        // Clear route cache
        \Artisan::call('route:clear');

        // Dispatch browser event to trigger navigation refresh
        $this->dispatch('navigation-updated');
    }

    /**
     * Check if a role has permission to access a given resource or page.
     */
    public function hasPermissionForResource(string $resourceClass, Role $role): bool
    {
        if (! class_exists($resourceClass)) {
            return false;
        }

        // Determine which method to use (canViewAny for Resources, canAccess for Pages)
        $hasCanViewAny = method_exists($resourceClass, 'canViewAny');
        $hasCanAccess = method_exists($resourceClass, 'canAccess');

        if (! $hasCanViewAny && ! $hasCanAccess) {
            return true; // Default to visible if no permission check exists
        }

        // Temporarily authenticate as a user with this role to check permissions
        $user = \App\Models\User::whereHas('roles', function ($query) use ($role) {
            $query->where('id', $role->id);
        })->first();

        if (! $user) {
            // Create a mock user with this role for permission checking
            $user = new \App\Models\User();
            $user->setRelation('roles', collect([$role]));
        }

        // Temporarily set the user as authenticated
        $previousUser = auth()->user();
        auth()->setUser($user);

        // Check if user can access the resource/page
        if ($hasCanViewAny) {
            $canAccess = $resourceClass::canViewAny();
        } else {
            $canAccess = $resourceClass::canAccess();
        }

        // Restore previous user
        if ($previousUser) {
            auth()->setUser($previousUser);
        } else {
            auth()->logout();
        }

        return $canAccess;
    }

    public function render()
    {
        $roles = Role::all();

        return view('livewire.navigation-manager', [
            'roles' => $roles,
            'filteredItems' => $this->getFilteredItemsProperty(),
            'previewNavigation' => $this->getPreviewNavigationProperty(),
        ]);
    }
}
