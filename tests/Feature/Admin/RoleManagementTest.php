<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.roles.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.roles.index';
    }

    /**
     * Test guest is redirected to login from the role list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the role-list permission cannot access the role list.
     */
    public function test_user_without_permission_cannot_access_role_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the role list.
     */
    public function test_admin_can_view_role_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create a role with permissions.
     */
    public function test_admin_can_create_role_with_permissions(): void
    {
        $permission = Permission::where('name', 'user-list')->firstOrFail();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.roles.store'), [
                'name' => 'Manager',
                'permissions' => [$permission->id],
            ]);

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('roles', ['name' => 'Manager']);
        $this->assertTrue(Role::where('name', 'Manager')->first()->hasPermissionTo('user-list'));
    }

    /**
     * Test a system role cannot be renamed.
     */
    public function test_system_role_cannot_be_renamed(): void
    {
        $adminRole = Role::where('name', 'Admin')->firstOrFail();

        $response = $this->actingAs($this->admin())
            ->from(route('admin.roles.edit', $adminRole))
            ->put(route('admin.roles.update', $adminRole), [
                'name' => 'RenamedAdmin',
                'permissions' => [],
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'name' => 'Admin']);
        $this->assertDatabaseMissing('roles', ['name' => 'RenamedAdmin']);
    }

    /**
     * Test a system role cannot be deleted.
     */
    public function test_system_role_cannot_be_deleted(): void
    {
        $userRole = Role::where('name', 'User')->firstOrFail();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.roles.destroy', $userRole));

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $userRole->id]);
    }

    /**
     * Test a non-system role can be deleted.
     */
    public function test_non_system_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
