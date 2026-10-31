<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default roles, permissions, and demo users
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->service = new UserService;
    }

    private function superAdmin(): User
    {
        return User::where('email', 'superadmin@example.com')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /**
     * Test creating a user assigns the given roles.
     */
    public function test_create_user_assigns_given_roles(): void
    {
        $user = $this->service->createUser([
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'secret123',
            'roles' => ['Admin'],
        ]);

        $this->assertDatabaseHas('users', ['email' => 'new-admin@example.com']);
        $this->assertTrue($user->hasRole('Admin'));
    }

    /**
     * Test creating a user without roles assigns the default User role.
     */
    public function test_create_user_without_roles_gets_default_user_role(): void
    {
        $user = $this->service->createUser([
            'name' => 'Plain User',
            'email' => 'plain@example.com',
            'password' => 'secret123',
        ]);

        $this->assertTrue($user->hasRole('User'));
    }

    /**
     * Test creating a user stores a hashed password.
     */
    public function test_create_user_hashes_the_password(): void
    {
        $user = $this->service->createUser([
            'name' => 'Hashed User',
            'email' => 'hashed@example.com',
            'password' => 'secret123',
        ]);

        $this->assertNotSame('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    /**
     * Test updating a user changes name, email, and roles.
     */
    public function test_update_user_changes_name_email_and_roles(): void
    {
        $user = User::factory()->create();

        $updated = $this->service->updateUser($user, [
            'name' => 'Renamed User',
            'email' => 'renamed@example.com',
            'roles' => ['Admin'],
        ]);

        $this->assertSame('Renamed User', $updated->name);
        $this->assertSame('renamed@example.com', $updated->email);
        $this->assertTrue($updated->hasRole('Admin'));
    }

    /**
     * Test updating a user keeps the password when none is given
     * and rehashes it when a new one is provided.
     */
    public function test_update_user_password_handling(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $this->assertSame($originalHash, $user->fresh()->password);

        $this->service->updateUser($user, [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'brand-new-secret',
        ]);
        $this->assertTrue(Hash::check('brand-new-secret', $user->fresh()->password));
    }

    /**
     * Test the seed Super Admin email cannot be changed.
     */
    public function test_update_user_cannot_change_seed_super_admin_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->updateUser($this->superAdmin(), [
            'name' => 'Super Admin',
            'email' => 'moved@example.com',
        ]);
    }

    /**
     * Test updating the seed Super Admin keeps the SuperAdmin role
     * even when it is missing from the given roles.
     */
    public function test_update_seed_super_admin_keeps_super_admin_role(): void
    {
        $superAdmin = $this->superAdmin();

        $this->service->updateUser($superAdmin, [
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'roles' => ['User'],
        ]);

        $this->assertTrue($superAdmin->fresh()->hasRole('SuperAdmin'));
    }

    /**
     * Test deleting a regular user removes the record.
     */
    public function test_delete_user_removes_a_regular_user(): void
    {
        $this->actingAs($this->admin());
        $victim = User::factory()->create();

        $result = $this->service->deleteUser($victim);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    /**
     * Test a user cannot delete their own account.
     */
    public function test_delete_user_cannot_delete_own_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->expectException(LogicException::class);

        $this->service->deleteUser($admin);
    }

    /**
     * Test the seed Super Admin account cannot be deleted.
     */
    public function test_delete_user_cannot_delete_seed_super_admin(): void
    {
        $this->actingAs($this->admin());

        $this->expectException(LogicException::class);

        $this->service->deleteUser($this->superAdmin());
    }
}
