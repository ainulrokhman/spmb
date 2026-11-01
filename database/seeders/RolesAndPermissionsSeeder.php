<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\SystemAccess;
use Illuminate\Database\Seeder;
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

            'program-list',
            'program-create',
            'program-edit',
            'program-delete',

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

            'admission-wave-list',
            'admission-wave-create',
            'admission-wave-edit',
            'admission-wave-delete',

            'document-type-list',
            'document-type-create',
            'document-type-edit',
            'document-type-delete',

            'form-field-list',
            'form-field-create',
            'form-field-edit',
            'form-field-delete',

            'fee-component-list',
            'fee-component-create',
            'fee-component-edit',
            'fee-component-delete',

            'fee-amount-list',
            'fee-amount-create',
            'fee-amount-edit',
            'fee-amount-delete',

            'form-payment-list',
            'form-payment-decide',

            'application-list',
            'application-verify',

            'invoice-list',
            'invoice-manage',

            'unit-fee-manage',
            'unit-requirement-manage',

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

        // Wali Role: guardians register themselves and only use the
        // guardian area, which is guarded by role, not permissions.
        Role::firstOrCreate(['name' => SystemAccess::WALI_ROLE]);

        // Panitia Role: unit committee members. They run the
        // admission process and manage the master data of their
        // assigned units; unit scoping is enforced by the
        // controllers, so these permissions only ever reach the
        // units on their assignment list.
        $panitiaRole = Role::firstOrCreate(['name' => SystemAccess::PANITIA_ROLE]);
        $panitiaRole->givePermissionTo([
            'view-dashboard',

            'school-unit-list',

            'admission-track-list',
            'admission-track-create',
            'admission-track-edit',
            'admission-track-delete',

            'major-list',
            'major-create',
            'major-edit',
            'major-delete',

            'admission-wave-list',
            'admission-wave-create',
            'admission-wave-edit',
            'admission-wave-delete',

            'form-field-list',
            'form-field-create',
            'form-field-edit',
            'form-field-delete',

            'fee-amount-list',
            'fee-amount-create',
            'fee-amount-edit',
            'fee-amount-delete',

            'unit-fee-manage',
            'unit-requirement-manage',

            'form-payment-list',
            'form-payment-decide',

            'application-list',
            'application-verify',

            'invoice-list',
            'invoice-manage',
        ]);

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

            'program-list',
            'program-create',
            'program-edit',
            'program-delete',

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

            'admission-wave-list',
            'admission-wave-create',
            'admission-wave-edit',
            'admission-wave-delete',

            'document-type-list',
            'document-type-create',
            'document-type-edit',
            'document-type-delete',

            'form-field-list',
            'form-field-create',
            'form-field-edit',
            'form-field-delete',

            'fee-component-list',
            'fee-component-create',
            'fee-component-edit',
            'fee-component-delete',

            'fee-amount-list',
            'fee-amount-create',
            'fee-amount-edit',
            'fee-amount-delete',

            'form-payment-list',
            'form-payment-decide',

            'application-list',
            'application-verify',

            'invoice-list',
            'invoice-manage',

            'unit-fee-manage',
            'unit-requirement-manage',

            'manage-settings',
        ]);

        // SuperAdmin Role: no explicit permissions are assigned
        // because it bypasses checks via Gate::before in
        // AppServiceProvider. User accounts are NOT seeded here —
        // the first Super Admin comes from InitialAdminSeeder
        // (.env-driven, production-safe) and demo accounts live in
        // DemoUserSeeder (testing only).
        Role::firstOrCreate(['name' => SystemAccess::SUPER_ADMIN_ROLE]);
    }
}
