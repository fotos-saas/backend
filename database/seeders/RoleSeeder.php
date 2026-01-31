<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeder for creating Spatie Permission roles.
 *
 * TODO: Telepítési lépések (NE FUTTASD MOST):
 * 1. composer install
 * 2. php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
 * 3. php artisan migrate
 * 4. php artisan db:seed --class=RoleSeeder
 * 5. php artisan db:seed --class=MigrateUserRolesToSpatieSeeder
 * 6. php artisan optimize:clear
 * 7. docker compose restart queue-worker (ha Docker-ben vagy)
 */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'super_admin',
            'photo_admin',
            'customer',
            'guest',
            'tablo',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web']
            );
        }
    }
}
