<?php

namespace Database\Seeders;

use App\Services\NavigationConfigService;
use Illuminate\Database\Seeder;

/**
 * Seeder for initializing default navigation groups.
 *
 * Creates system navigation groups that organize menu items
 * (e.g., "Platform Settings", "Shipping & Payment", "Email System").
 */
class NavigationGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = app(NavigationConfigService::class);
        $service->initializeDefaultGroups();

        $this->command->info('✓ Default navigation groups initialized successfully.');
    }
}
