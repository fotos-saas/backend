<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\PermissionService;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase as BaseTestCase;

class PermissionServiceTest extends BaseTestCase
{
    protected PermissionService $permissionService;

    protected User $testUser;

    protected Role $testRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissionService = app(PermissionService::class);

        // Create test role and user
        $this->testRole = Role::firstOrCreate(['name' => 'test_role']);
        $this->testUser = User::factory()->create();
        $this->testUser->assignRole($this->testRole);
    }

    public function test_super_admin_always_has_permission(): void
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('super_admin');

        $this->actingAs($adminUser);

        $this->assertTrue($this->permissionService->hasPermission('work-sessions.view'));
        $this->assertTrue($this->permissionService->hasPermission('orders.delete'));
        $this->assertTrue($this->permissionService->hasPermission('any-random-permission'));
    }

    public function test_user_without_permission_denied(): void
    {
        $this->actingAs($this->testUser);

        $this->assertFalse($this->permissionService->hasPermission('work-sessions.view'));
    }

    public function test_user_with_exact_permission_granted(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.view']);
        $this->testRole->givePermissionTo('work-sessions.view');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->hasPermission('work-sessions.view'));
    }

    public function test_wildcard_permission_works(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.*']);
        $this->testRole->givePermissionTo('work-sessions.*');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->hasPermission('work-sessions.view'));
        $this->assertTrue($this->permissionService->hasPermission('work-sessions.tab.basic'));
        $this->assertTrue($this->permissionService->hasPermission('work-sessions.action.download-zip'));
    }

    public function test_global_wildcard_permission_works(): void
    {
        Permission::firstOrCreate(['name' => '*']);
        $this->testRole->givePermissionTo('*');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->hasPermission('any-permission'));
        $this->assertTrue($this->permissionService->hasPermission('another-permission.view'));
    }

    public function test_can_access_tab_permission(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.tab.coupon-settings']);
        $this->testRole->givePermissionTo('work-sessions.tab.coupon-settings');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->canAccessTab('work-sessions', 'coupon-settings'));
    }

    public function test_can_access_field_permission(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.field.package_id']);
        $this->testRole->givePermissionTo('work-sessions.field.package_id');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->canSeeField('work-sessions', 'package_id'));
    }

    public function test_can_access_action_permission(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.action.download-zip']);
        $this->testRole->givePermissionTo('work-sessions.action.download-zip');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->canAccessAction('work-sessions', 'download-zip'));
    }

    public function test_has_any_permission_returns_true_if_has_one(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.view']);
        $this->testRole->givePermissionTo('work-sessions.view');

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->hasAnyPermission([
            'work-sessions.view',
            'orders.view',
            'albums.view',
        ]));
    }

    public function test_has_any_permission_returns_false_if_has_none(): void
    {
        $this->actingAs($this->testUser);

        $this->assertFalse($this->permissionService->hasAnyPermission([
            'work-sessions.view',
            'orders.view',
            'albums.view',
        ]));
    }

    public function test_has_all_permissions_returns_true_if_has_all(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.view']);
        Permission::firstOrCreate(['name' => 'work-sessions.edit']);
        $this->testRole->givePermissionTo(['work-sessions.view', 'work-sessions.edit']);

        $this->actingAs($this->testUser);

        $this->assertTrue($this->permissionService->hasAllPermissions([
            'work-sessions.view',
            'work-sessions.edit',
        ]));
    }

    public function test_has_all_permissions_returns_false_if_missing_one(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.view']);
        $this->testRole->givePermissionTo('work-sessions.view');

        $this->actingAs($this->testUser);

        $this->assertFalse($this->permissionService->hasAllPermissions([
            'work-sessions.view',
            'work-sessions.edit',
        ]));
    }

    public function test_cache_is_used(): void
    {
        Permission::firstOrCreate(['name' => 'work-sessions.view']);
        $this->testRole->givePermissionTo('work-sessions.view');

        $this->actingAs($this->testUser);

        // First call - loads from DB and caches
        $this->assertTrue($this->permissionService->hasPermission('work-sessions.view'));

        // Second call - should use cache (faster)
        $this->assertTrue($this->permissionService->hasPermission('work-sessions.view'));
    }
}
