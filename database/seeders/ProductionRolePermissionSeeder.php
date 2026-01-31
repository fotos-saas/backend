<?php

namespace Database\Seeders;

use App\Models\NavigationGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Production Role, Permission, Navigation Group, and User Seeder
 *
 * This seeder exports the complete role-permission-navigation structure from
 * local development to production environment.
 *
 * Usage:
 *   php artisan db:seed --class=ProductionRolePermissionSeeder --force
 */
class ProductionRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting Production Role-Permission-Navigation Seeder...');

        // 1. Create all permissions first
        $this->createPermissions();

        // 2. Create roles with their permissions
        $this->createRolesWithPermissions();

        // 3. Create navigation groups
        $this->createNavigationGroups();

        // 4. Create/Update admin users
        $this->createAdminUsers();

        $this->command->info('✅ Production Role-Permission-Navigation Seeder completed!');
    }

    /**
     * Create all permissions
     */
    protected function createPermissions(): void
    {
        $this->command->info('📋 Creating permissions...');

        $allPermissions = $this->getAllPermissions();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        $this->command->info("   ✅ Created " . count($allPermissions) . " permissions");
    }

    /**
     * Create roles with their assigned permissions
     */
    protected function createRolesWithPermissions(): void
    {
        $this->command->info('👥 Creating roles with permissions...');

        // Super Admin Role
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($this->getSuperAdminPermissions());
        $this->command->info('   ✅ Super Admin role with ' . count($this->getSuperAdminPermissions()) . ' permissions');

        // Photo Admin Role
        $photoAdmin = Role::firstOrCreate(['name' => 'photo_admin', 'guard_name' => 'web']);
        $photoAdmin->syncPermissions($this->getPhotoAdminPermissions());
        $this->command->info('   ✅ Photo Admin role with ' . count($this->getPhotoAdminPermissions()) . ' permissions');

        // Tablo Role
        $tablo = Role::firstOrCreate(['name' => 'tablo', 'guard_name' => 'web']);
        $tablo->syncPermissions($this->getTabloPermissions());
        $this->command->info('   ✅ Tablo role with ' . count($this->getTabloPermissions()) . ' permissions');

        // Marketer Role
        $marketer = Role::firstOrCreate(['name' => 'marketer', 'guard_name' => 'web']);
        $marketer->syncPermissions($this->getMarketerPermissions());
        $this->command->info('   ✅ Marketer role with ' . count($this->getMarketerPermissions()) . ' permissions');

        // Partner Role (Fotós)
        $partner = Role::firstOrCreate(['name' => 'partner', 'guard_name' => 'web']);
        $partner->syncPermissions($this->getPartnerPermissions());
        $this->command->info('   ✅ Partner role with ' . count($this->getPartnerPermissions()) . ' permissions');

        // Customer Role (no permissions)
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $this->command->info('   ✅ Customer role');

        // Guest Role (no permissions)
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        $this->command->info('   ✅ Guest role');

        // User Role (no permissions)
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $this->command->info('   ✅ User role');
    }

    /**
     * Create navigation groups
     */
    protected function createNavigationGroups(): void
    {
        $this->command->info('🗂️  Creating navigation groups...');

        $tabloRole = Role::where('name', 'tablo')->first();

        NavigationGroup::updateOrCreate(
            ['key' => 'fotozas'],
            [
                'role_id' => $tabloRole?->id,
                'label' => 'Fotózás',
                'sort_order' => 20,
                'is_system' => false,
                'collapsed' => false,
            ]
        );

        NavigationGroup::updateOrCreate(
            ['key' => 'shipping-payment'],
            [
                'role_id' => null,
                'label' => 'Szállítás és Fizetés',
                'sort_order' => 50,
                'is_system' => true,
                'collapsed' => true,
            ]
        );

        NavigationGroup::updateOrCreate(
            ['key' => 'email-system'],
            [
                'role_id' => null,
                'label' => 'Email Rendszer',
                'sort_order' => 90,
                'is_system' => true,
                'collapsed' => true,
            ]
        );

        NavigationGroup::updateOrCreate(
            ['key' => 'platform-settings'],
            [
                'role_id' => null,
                'label' => 'Platform Beállítások',
                'sort_order' => 100,
                'is_system' => true,
                'collapsed' => true,
            ]
        );

        $this->command->info('   ✅ Created 4 navigation groups');
    }

    /**
     * Create or update admin users
     */
    protected function createAdminUsers(): void
    {
        $this->command->info('👤 Creating/Updating admin users...');

        // Super Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Hello1990'),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }
        $this->command->info('   ✅ Super Admin user (admin@example.com)');

        // Tablo User
        $tablo = User::firstOrCreate(
            ['email' => 'nove.ferenc+22@gmail.com'],
            [
                'name' => 'ballagasitablo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Update name if exists
        if ($tablo->name !== 'ballagasitablo') {
            $tablo->update(['name' => 'ballagasitablo']);
        }

        if (! $tablo->hasRole('tablo')) {
            $tablo->assignRole('tablo');
        }
        $this->command->info('   ✅ Tablo user (nove.ferenc+22@gmail.com)');

        // Marketer User
        $marketer = User::firstOrCreate(
            ['email' => 'marketer@example.com'],
            [
                'name' => 'Marketinges',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $marketer->hasRole('marketer')) {
            $marketer->assignRole('marketer');
        }

        // Ballagásitabló = Spotfotó partner (ID: 1) hozzárendelése
        $ballagasiTabloPartner = \App\Models\TabloPartner::where('slug', 'ballagasitablo-fotos')->first();
        if ($ballagasiTabloPartner && $marketer->tablo_partner_id !== $ballagasiTabloPartner->id) {
            $marketer->update(['tablo_partner_id' => $ballagasiTabloPartner->id]);
        }

        $this->command->info('   ✅ Marketer user (marketer@example.com)');
    }

    /**
     * Get all unique permissions from all roles
     */
    protected function getAllPermissions(): array
    {
        return array_values(array_unique(array_merge(
            $this->getSuperAdminPermissions(),
            $this->getPhotoAdminPermissions(),
            $this->getTabloPermissions(),
            $this->getMarketerPermissions(),
            $this->getPartnerPermissions()
        )));
    }

    /**
     * Super Admin permissions
     */
    protected function getSuperAdminPermissions(): array
    {
        return [
            'dashboard.view',
            'dashboard.*',
            'work-sessions.view',
            'work-sessions.create',
            'work-sessions.edit',
            'work-sessions.delete',
            'work-sessions.tab.basic',
            'work-sessions.tab.access-methods',
            'work-sessions.tab.coupon-settings',
            'work-sessions.tab.pricing',
            'work-sessions.tab.tablo-mode',
            'work-sessions.action.download-zip',
            'work-sessions.action.duplicate',
            'work-sessions.relation.users',
            'work-sessions.relation.albums',
            'work-sessions.relation.child-sessions',
            'work-sessions.*',
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',
            'orders.tab.basic',
            'orders.tab.items',
            'orders.tab.payment',
            'orders.action.generate-invoice',
            'orders.action.send-email',
            'orders.action.refund',
            'orders.relation.items',
            'orders.*',
            'albums.view',
            'albums.create',
            'albums.edit',
            'albums.delete',
            'albums.tab.basic',
            'albums.tab.photos',
            'albums.tab.settings',
            'albums.relation.photos',
            'albums.relation.users',
            'albums.*',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.relation.photos',
            'users.*',
            'admin-users.view',
            'admin-users.create',
            'admin-users.edit',
            'admin-users.delete',
            'admin-users.*',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.*',
            'packages.view',
            'packages.create',
            'packages.edit',
            'packages.delete',
            'packages.relation.items',
            'packages.*',
            'price-lists.view',
            'price-lists.create',
            'price-lists.edit',
            'price-lists.delete',
            'price-lists.relation.prices',
            'price-lists.*',
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'coupons.delete',
            'coupons.*',
            'shipping-methods.view',
            'shipping-methods.edit',
            'shipping-methods.delete',
            'shipping-methods.*',
            'payment-methods.view',
            'payment-methods.edit',
            'payment-methods.delete',
            'payment-methods.*',
            'package-points.view',
            'package-points.edit',
            'package-points.delete',
            'package-points.*',
            'email-templates.view',
            'email-templates.create',
            'email-templates.edit',
            'email-templates.delete',
            'email-templates.action.preview',
            'email-templates.*',
            'email-events.view',
            'email-events.create',
            'email-events.edit',
            'email-events.delete',
            'email-events.*',
            'email-logs.view',
            'email-logs.delete',
            'email-logs.*',
            'smtp-accounts.view',
            'smtp-accounts.create',
            'smtp-accounts.edit',
            'smtp-accounts.delete',
            'smtp-accounts.*',
            'invoicing-providers.view',
            'invoicing-providers.create',
            'invoicing-providers.edit',
            'invoicing-providers.delete',
            'invoicing-providers.*',
            'partner-settings.view',
            'partner-settings.edit',
            'partner-settings.*',
            'stripe-settings.view',
            'stripe-settings.edit',
            'stripe-settings.*',
            'queue-management.view',
            'queue-management.*',
            '*',
            'guest-share-tokens.view',
            'guest-share-tokens.create',
            'guest-share-tokens.edit',
            'guest-share-tokens.delete',
            'guest-share-tokens.*',
            'print-sizes.view',
            'print-sizes.create',
            'print-sizes.edit',
            'print-sizes.delete',
            'print-sizes.*',
            'email-variables.view',
            'email-variables.*',
            'settings.view',
            'settings.edit',
            'settings.*',
            'navigation.manage',
            'navigation.*',
            'photos.view',
            'photos.create',
            'photos.edit',
            'photos.delete',
            'photos.*',
            'navigation-managers.view',
            'navigation-managers.edit',
            'navigation-managers.*',
            'permission-management.view',
            'permission-management.edit',
            'permission-management.*',
        ];
    }

    /**
     * Photo Admin permissions
     */
    protected function getPhotoAdminPermissions(): array
    {
        return [
            'dashboard.view',
            'work-sessions.view',
            'work-sessions.create',
            'work-sessions.edit',
            'work-sessions.delete',
            'work-sessions.tab.basic',
            'work-sessions.tab.access-methods',
            'work-sessions.tab.coupon-settings',
            'work-sessions.tab.pricing',
            'work-sessions.tab.tablo-mode',
            'work-sessions.action.download-zip',
            'work-sessions.action.duplicate',
            'work-sessions.relation.users',
            'work-sessions.relation.albums',
            'work-sessions.relation.child-sessions',
            'work-sessions.*',
            'albums.view',
            'albums.create',
            'albums.edit',
            'albums.delete',
            'albums.tab.basic',
            'albums.tab.photos',
            'albums.tab.settings',
            'albums.relation.photos',
            'albums.relation.users',
            'albums.*',
            'users.view',
            'users.edit',
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',
            'orders.tab.basic',
            'orders.tab.items',
            'orders.tab.payment',
            'orders.action.generate-invoice',
            'orders.action.send-email',
            'orders.action.refund',
            'orders.relation.items',
            'orders.*',
            'packages.view',
            'packages.create',
            'packages.edit',
            'packages.delete',
            'packages.relation.items',
            'packages.*',
            'price-lists.view',
            'price-lists.create',
            'price-lists.edit',
            'price-lists.delete',
            'price-lists.relation.prices',
            'price-lists.*',
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'coupons.delete',
            'coupons.*',
        ];
    }

    /**
     * Tablo permissions
     */
    protected function getTabloPermissions(): array
    {
        return [
            'work-sessions.view',
            'work-sessions.create',
            'work-sessions.edit',
            'work-sessions.delete',
            'work-sessions.tab.basic',
            'work-sessions.tab.access-methods',
            'work-sessions.tab.tablo-mode',
            'work-sessions.relation.users',
            'work-sessions.relation.albums',
            'albums.view',
            'albums.create',
            'albums.edit',
            'albums.delete',
            'albums.tab.basic',
            'albums.relation.photos',
            'albums.relation.users',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.relation.photos',
        ];
    }

    /**
     * Marketer permissions
     */
    protected function getMarketerPermissions(): array
    {
        return [
            'marketer.dashboard',
            'marketer.projects.view',
            'marketer.schools.view',
            'marketer.qr-codes.view',
            'marketer.qr-codes.create',
        ];
    }

    /**
     * Partner (Fotós) permissions
     */
    protected function getPartnerPermissions(): array
    {
        return [
            'partner.dashboard',
            'partner.projects.view',
            // Később bővíthető: partner.calendar.view, partner.templates.view, stb.
        ];
    }
}
