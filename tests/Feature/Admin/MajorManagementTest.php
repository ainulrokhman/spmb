<?php

namespace Tests\Feature\Admin;

use App\Models\Major;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class MajorManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.majors.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.majors.index';
    }

    /**
     * Test guest is redirected to login from the major list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the major-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_major_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the major list.
     */
    public function test_admin_can_view_major_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create a major for a unit.
     */
    public function test_admin_can_create_major(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.majors.store'), [
                'school_unit_id' => $unit->id,
                'name' => 'Teknik Komputer dan Jaringan',
                'code' => 'TKJ',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.majors.index'));
        $this->assertDatabaseHas('majors', ['school_unit_id' => $unit->id, 'name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ']);
    }

    /**
     * Test a major name must be unique within its unit but may repeat across units.
     */
    public function test_major_name_is_unique_per_unit_only(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();
        Major::factory()->create(['school_unit_id' => $unitA->id, 'name' => 'IPA']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.majors.store'), [
                'school_unit_id' => $unitA->id,
                'name' => 'IPA',
            ]);
        $response->assertSessionHasErrors('name');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.majors.store'), [
                'school_unit_id' => $unitB->id,
                'name' => 'IPA',
            ]);
        $response->assertRedirect(route('admin.majors.index'));
    }

    /**
     * Test a major requires an existing school unit.
     */
    public function test_major_requires_an_existing_school_unit(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.majors.store'), [
                'school_unit_id' => 999,
                'name' => 'IPA',
            ]);

        $response->assertSessionHasErrors('school_unit_id');
    }

    /**
     * Test an admin can update a major.
     */
    public function test_admin_can_update_major(): void
    {
        $major = Major::factory()->create(['name' => 'IPA']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.majors.update', $major), [
                'school_unit_id' => $major->school_unit_id,
                'name' => 'IPA Unggulan',
                'code' => $major->code,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.majors.index'));
        $this->assertDatabaseHas('majors', ['id' => $major->id, 'name' => 'IPA Unggulan']);
    }

    /**
     * Test an admin can delete a major.
     */
    public function test_admin_can_delete_major(): void
    {
        $major = Major::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.majors.destroy', $major));

        $response->assertRedirect(route('admin.majors.index'));
        $this->assertDatabaseMissing('majors', ['id' => $major->id]);
    }
}
