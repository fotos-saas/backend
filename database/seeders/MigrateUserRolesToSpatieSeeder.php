<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeder for migrating existing user roles to Spatie Permission.
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
class MigrateUserRolesToSpatieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, create roles from existing distinct role values in users table
        $existingRoles = User::distinct('role')
            ->whereNotNull('role')
            ->pluck('role');

        foreach ($existingRoles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['guard_name' => 'web']
            );
        }

        // Then assign each user their role in the Spatie Permission system
        User::whereNotNull('role')->chunk(100, function ($users) {
            foreach ($users as $user) {
                if (! $user->hasRole($user->role)) {
                    $user->assignRole($user->role);
                }
            }
        });
    }
}
