<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.permissions.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.permissions.index';
    }

    /**
     * The seeded core permission the protection rules apply to.
     */
    private function protectedPermission(): Permission
    {
        return Permission::where('name', 'user-list')->firstOrFail();
    }

    /**
     * Create a custom permission outside the protected core set.
     */
    private function createCustomPermission(): Permission
    {
        return Permission::create(['name' => 'custom-thing', 'guard_name' => 'web']);
    }

    /**
     * Test guest is redirected to login from the permission list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the permission-list permission cannot access the permission list.
     */
    public function test_user_without_permission_cannot_access_permission_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the permission list.
     */
    public function test_admin_can_view_permission_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin cannot create a permission because the Admin role
     * only holds permission-list, not permission-create.
     */
    public function test_admin_cannot_create_permission(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.permissions.store'), [
                'name' => 'export-reports',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('permissions', ['name' => 'export-reports']);
    }

    /**
     * Test a Super Admin can create a permission and its name is stored lowercase.
     */
    public function test_superadmin_can_create_permission_and_name_is_lowercased(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->post(route('admin.permissions.store'), [
                'name' => 'Export-Reports',
            ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('permissions', ['name' => 'export-reports']);
    }

    /**
     * Test a core system permission cannot be updated.
     */
    public function test_protected_permission_cannot_be_updated(): void
    {
        $permission = $this->protectedPermission();

        $response = $this->actingAs($this->superAdmin())
            ->put(route('admin.permissions.update', $permission), [
                'name' => 'user-list-renamed',
            ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'user-list']);
    }

    /**
     * Test a core system permission cannot be deleted.
     */
    public function test_protected_permission_cannot_be_deleted(): void
    {
        $permission = $this->protectedPermission();

        $response = $this->actingAs($this->superAdmin())
            ->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    /**
     * Test a custom permission can be updated.
     */
    public function test_custom_permission_can_be_updated(): void
    {
        $permission = $this->createCustomPermission();

        $response = $this->actingAs($this->superAdmin())
            ->put(route('admin.permissions.update', $permission), [
                'name' => 'custom-renamed',
            ]);

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'custom-renamed']);
    }

    /**
     * Test a custom permission can be deleted.
     */
    public function test_custom_permission_can_be_deleted(): void
    {
        $permission = $this->createCustomPermission();

        $response = $this->actingAs($this->superAdmin())
            ->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirect(route('admin.permissions.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }
}
