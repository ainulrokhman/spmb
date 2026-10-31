<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RoleService;
use App\Services\PermissionService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionServiceUnitTest extends TestCase
{
    /**
     * Test system roles detection with string names.
     */
    public function test_is_system_role_works_with_string_names(): void
    {
        $roleService = new RoleService();

        $this->assertTrue($roleService->isSystemRole('SuperAdmin'));
        $this->assertTrue($roleService->isSystemRole('Admin'));
        $this->assertTrue($roleService->isSystemRole('User'));
        $this->assertFalse($roleService->isSystemRole('CustomRole'));
    }

    /**
     * Test system roles detection with Role models.
     */
    public function test_is_system_role_works_with_role_model_in_memory(): void
    {
        $roleService = new RoleService();

        // Create models in memory (no DB save)
        $superAdminRole = new Role();
        $superAdminRole->name = 'SuperAdmin';

        $customRole = new Role();
        $customRole->name = 'CustomRole';

        $this->assertTrue($roleService->isSystemRole($superAdminRole));
        $this->assertFalse($roleService->isSystemRole($customRole));
    }

    /**
     * Test protected permissions detection.
     */
    public function test_is_permission_protected_works_with_permission_model_in_memory(): void
    {
        $permissionService = new PermissionService();

        $viewDashboard = new Permission();
        $viewDashboard->name = 'view-dashboard';

        $customPermission = new Permission();
        $customPermission->name = 'custom-permission';

        $this->assertTrue($permissionService->isPermissionProtected($viewDashboard));
        $this->assertFalse($permissionService->isPermissionProtected($customPermission));
    }
}
