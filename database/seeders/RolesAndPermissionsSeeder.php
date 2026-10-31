<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            'view-dashboard',

            'user-list',
            'user-create',
            'user-edit',
            'user-delete',

            'role-list',
            'role-create',
            'role-edit',
            'role-delete',

            'permission-list',
            'permission-create',
            'permission-edit',
            'permission-delete',

            'academic-year-list',
            'academic-year-create',
            'academic-year-edit',
            'academic-year-delete',

            'school-unit-list',
            'school-unit-create',
            'school-unit-edit',
            'school-unit-delete',

            'admission-track-list',
            'admission-track-create',
            'admission-track-edit',
            'admission-track-delete',

            'major-list',
            'major-create',
            'major-edit',
            'major-delete',

            'manage-settings',
            'activity-log-list',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Create Roles and Assign Existing Permissions

        // User Role
        $userRole = Role::firstOrCreate(['name' => SystemAccess::USER_ROLE]);
        $userRole->givePermissionTo('view-dashboard');

        // Admin Role
        $adminRole = Role::firstOrCreate(['name' => SystemAccess::ADMIN_ROLE]);
        $adminRole->givePermissionTo([
            'view-dashboard',

            'user-list',
            'user-create',
            'user-edit',
            'user-delete',

            'role-list',
            'role-create',
            'role-edit',
            'role-delete',

            'permission-list',
            'activity-log-list',

            'academic-year-list',
            'academic-year-create',
            'academic-year-edit',
            'academic-year-delete',

            'school-unit-list',
            'school-unit-create',
            'school-unit-edit',
            'school-unit-delete',

            'admission-track-list',
            'admission-track-create',
            'admission-track-edit',
            'admission-track-delete',

            'major-list',
            'major-create',
            'major-edit',
            'major-delete',
        ]);

        // SuperAdmin Role
        $superAdminRole = Role::firstOrCreate(['name' => SystemAccess::SUPER_ADMIN_ROLE]);
        // No explicit permissions assigned because it bypasses checks via Gate::before in AppServiceProvider

        // 3. Create Default Users for Testing

        // SuperAdmin User
        $superAdmin = User::firstOrCreate(
            ['email' => SystemAccess::SUPER_ADMIN_EMAIL],
            [
                'name' => 'Super Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        // Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole($adminRole);

        // Regular User
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
            ]
        );
        $user->assignRole($userRole);
    }
}
