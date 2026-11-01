<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class UserUnitAssignmentTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    /**
     * Test an admin can assign school units when creating a user.
     */
    public function test_admin_can_assign_units_when_creating_user(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.users.store'), [
                'name' => 'Panitia Satu',
                'email' => 'panitia@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['User'],
                'school_units' => [$unitA->id, $unitB->id],
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $user = User::where('email', 'panitia@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$unitA->id, $unitB->id],
            DB::table('user_school_units')->where('user_id', $user->id)->pluck('school_unit_id')->all()
        );
    }

    /**
     * Test updating a user replaces the unit assignments.
     */
    public function test_admin_can_change_unit_assignments_when_updating_user(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('User');
        $user->schoolUnits()->sync([$unitA->id]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => ['User'],
                'school_units' => [$unitB->id],
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertEqualsCanonicalizing(
            [$unitB->id],
            DB::table('user_school_units')->where('user_id', $user->id)->pluck('school_unit_id')->all()
        );
    }

    /**
     * Test an unknown school unit in the assignment is rejected.
     */
    public function test_invalid_unit_assignment_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.users.store'), [
                'name' => 'Panitia Dua',
                'email' => 'panitia2@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['User'],
                'school_units' => [999999],
            ]);

        $response->assertSessionHasErrors('school_units.0');
        $this->assertDatabaseMissing('users', ['email' => 'panitia2@example.com']);
    }

    /**
     * Test foundation-level roles access every unit without assignments.
     */
    public function test_global_roles_have_access_to_all_units(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();

        $this->assertTrue($this->admin()->hasGlobalUnitAccess());
        $this->assertTrue($this->admin()->hasUnitAccess($unitA));
        $this->assertTrue($this->superAdmin()->hasUnitAccess($unitB->id));
        $this->assertEqualsCanonicalizing(
            [$unitA->id, $unitB->id],
            $this->admin()->accessibleUnitIds()
        );
    }

    /**
     * Test a regular user only accesses the units they are assigned to.
     */
    public function test_scoped_user_only_accesses_assigned_units(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('User');
        $user->schoolUnits()->sync([$unitA->id]);

        $this->assertFalse($user->hasGlobalUnitAccess());
        $this->assertTrue($user->hasUnitAccess($unitA));
        $this->assertFalse($user->hasUnitAccess($unitB));
        $this->assertSame([$unitA->id], $user->accessibleUnitIds());
    }
}
