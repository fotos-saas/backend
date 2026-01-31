<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
            ]
        );

        // Assign Spatie role
        if (! $admin->hasRole(User::ROLE_SUPER_ADMIN)) {
            $admin->assignRole(User::ROLE_SUPER_ADMIN);
        }
    }
}
