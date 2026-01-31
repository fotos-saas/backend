<?php

namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TabloRoleTest extends TestCase
{
    protected User $tabloUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tablo user
        $this->tabloUser = User::factory()->create();
        $this->tabloUser->assignRole('tablo');
    }

    public function test_tablo_user_can_access_admin_panel(): void
    {
        $this->assertFalse($this->tabloUser->cannot('access', 'admin'));
    }

    public function test_tablo_user_can_only_see_dashboard(): void
    {
        $this->actingAs($this->tabloUser);

        // Dashboard should be visible
        $this->assertTrue(can_access_permission('dashboard.view'));

        // Other resources should NOT be visible
        $this->assertFalse(can_access_permission('work-sessions.view'));
        $this->assertFalse(can_access_permission('orders.view'));
        $this->assertFalse(can_access_permission('users.view'));
    }

    public function test_tablo_user_cannot_access_work_sessions(): void
    {
        $this->actingAs($this->tabloUser);

        $this->assertFalse(can_access_permission('work-sessions.view'));
        $this->assertFalse(can_access_permission('work-sessions.create'));
        $this->assertFalse(can_access_permission('work-sessions.edit'));
        $this->assertFalse(can_access_permission('work-sessions.delete'));
    }

    public function test_tablo_user_cannot_see_work_sessions_tabs(): void
    {
        $this->actingAs($this->tabloUser);

        $this->assertFalse(can_access_tab('munkamenetek', 'basic'));
        $this->assertFalse(can_access_tab('munkamenetek', 'coupon-settings'));
        $this->assertFalse(can_access_tab('munkamenetek', 'pricing'));
        $this->assertFalse(can_access_tab('munkamenetek', 'tablo-mode'));
    }

    public function test_tablo_user_cannot_access_orders(): void
    {
        $this->actingAs($this->tabloUser);

        $this->assertFalse(can_access_permission('orders.view'));
        $this->assertFalse(can_access_permission('orders.create'));
        $this->assertFalse(can_access_permission('orders.edit'));
    }

    public function test_tablo_user_cannot_access_users(): void
    {
        $this->actingAs($this->tabloUser);

        $this->assertFalse(can_access_permission('users.view'));
        $this->assertFalse(can_access_permission('users.create'));
        $this->assertFalse(can_access_permission('users.edit'));
    }

    public function test_tablo_user_cannot_manage_permissions(): void
    {
        $this->actingAs($this->tabloUser);

        $this->assertFalse(can_access_permission('roles.view'));
        $this->assertFalse(can_access_permission('roles.edit'));
    }

    public function test_super_admin_can_access_everything(): void
    {
        $adminUser = User::factory()->create();
        $adminUser->assignRole('super_admin');

        $this->actingAs($adminUser);

        $this->assertTrue(can_access_permission('work-sessions.view'));
        $this->assertTrue(can_access_permission('orders.delete'));
        $this->assertTrue(can_access_permission('users.edit'));
        $this->assertTrue(can_access_permission('roles.view'));
    }

    public function test_tablo_role_has_only_dashboard_permission(): void
    {
        $tabloRole = Role::findByName('tablo');
        $permissions = $tabloRole->permissions->pluck('name')->toArray();

        // Should have exactly 1 permission
        $this->assertCount(1, $permissions);
        $this->assertContains('dashboard.view', $permissions);
    }

    public function test_granting_permission_to_tablo_user_makes_it_visible(): void
    {
        // Initially tablo user cannot see work-sessions
        $this->actingAs($this->tabloUser);
        $this->assertFalse(can_access_permission('work-sessions.view'));

        // Grant permission
        $workSessionPermission = Permission::where('name', 'work-sessions.view')->first();
        $this->tabloUser->givePermissionTo($workSessionPermission);

        // Now tablo user can see work-sessions
        $this->assertTrue(can_access_permission('work-sessions.view'));
    }

    public function test_revoking_permission_from_tablo_user(): void
    {
        // Grant permission first
        $workSessionPermission = Permission::where('name', 'work-sessions.view')->first();
        $this->tabloUser->givePermissionTo($workSessionPermission);

        $this->actingAs($this->tabloUser);
        $this->assertTrue(can_access_permission('work-sessions.view'));

        // Revoke permission
        $this->tabloUser->revokePermissionTo($workSessionPermission);

        // Now tablo user cannot see work-sessions
        $this->assertFalse(can_access_permission('work-sessions.view'));
    }
}
