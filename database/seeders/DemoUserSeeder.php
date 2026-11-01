<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo staff accounts with the well-known password "password".
 *
 * TESTING ONLY — never call this seeder in production: the accounts
 * and their password are public knowledge. Production gets its first
 * Super Admin from InitialAdminSeeder instead.
 */
class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => SystemAccess::SUPER_ADMIN_EMAIL],
            [
                'name' => 'Super Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $superAdmin->assignRole(SystemAccess::SUPER_ADMIN_ROLE);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole(SystemAccess::ADMIN_ROLE);

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
            ]
        );
        $user->assignRole(SystemAccess::USER_ROLE);
    }
}
