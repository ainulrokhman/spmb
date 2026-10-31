<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class SchoolUnitManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.school-units.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.school-units.index';
    }

    /**
     * A valid school unit payload.
     */
    private function validUnitPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'SMP Reguler',
            'code' => '31',
            'level' => 'SMP/MTs',
            'program' => 'Reguler',
            'address' => 'Jl. Pendidikan No. 1',
            'phone' => '081234567890',
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * Test guest is redirected to login from the school unit list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the school-unit-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_school_unit_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the school unit list.
     */
    public function test_admin_can_view_school_unit_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test the school unit DataTable endpoint returns rows as JSON.
     */
    public function test_admin_can_fetch_school_units_datatable(): void
    {
        SchoolUnit::factory()->create(['name' => 'SMP Boarding', 'code' => '32']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.school-units.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'SMP Boarding']);
    }

    /**
     * Test an admin can create a school unit.
     */
    public function test_admin_can_create_school_unit(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.school-units.store'), $this->validUnitPayload());

        $response->assertRedirect(route('admin.school-units.index'));
        $this->assertDatabaseHas('school_units', ['code' => '31', 'level' => 'SMP/MTs', 'program' => 'Reguler']);
    }

    /**
     * Test the unit code must be exactly two digits.
     */
    public function test_unit_code_must_be_exactly_two_digits(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.school-units.store'), $this->validUnitPayload(['code' => '3']));

        $response->assertSessionHasErrors('code');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.school-units.store'), $this->validUnitPayload(['code' => 'AB']));

        $response->assertSessionHasErrors('code');
    }

    /**
     * Test the unit code must be unique.
     */
    public function test_unit_code_must_be_unique(): void
    {
        SchoolUnit::factory()->create(['code' => '31']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.school-units.store'), $this->validUnitPayload());

        $response->assertSessionHasErrors('code');
    }

    /**
     * Test the level must be one of the known levels.
     */
    public function test_unit_level_must_be_a_known_level(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.school-units.store'), $this->validUnitPayload(['level' => 'Universitas']));

        $response->assertSessionHasErrors('level');
    }

    /**
     * Test an admin can update a school unit keeping its own code.
     */
    public function test_admin_can_update_school_unit(): void
    {
        $unit = SchoolUnit::factory()->create(['code' => '31', 'program' => 'Reguler']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.school-units.update', $unit), $this->validUnitPayload(['name' => 'SMP Reguler Baru']));

        $response->assertRedirect(route('admin.school-units.index'));
        $this->assertDatabaseHas('school_units', ['id' => $unit->id, 'name' => 'SMP Reguler Baru', 'code' => '31']);
    }

    /**
     * Test an admin can delete a school unit.
     */
    public function test_admin_can_delete_school_unit(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.school-units.destroy', $unit));

        $response->assertRedirect(route('admin.school-units.index'));
        $this->assertDatabaseMissing('school_units', ['id' => $unit->id]);
    }
}
