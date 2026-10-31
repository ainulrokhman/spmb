<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class AcademicYearManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.academic-years.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.academic-years.index';
    }

    /**
     * Test guest is redirected to login from the academic year list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the academic-year-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_academic_year_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the academic year list.
     */
    public function test_admin_can_view_academic_year_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create an academic year.
     */
    public function test_admin_can_create_academic_year(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.academic-years.store'), [
                'name' => '2026/2027',
            ]);

        $response->assertRedirect(route('admin.academic-years.index'));
        $this->assertDatabaseHas('academic_years', ['name' => '2026/2027', 'is_active' => false]);
    }

    /**
     * Test creating an active academic year deactivates the previously active one.
     */
    public function test_creating_an_active_year_deactivates_the_previous_active_year(): void
    {
        $previous = AcademicYear::factory()->create(['name' => '2025/2026', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('admin.academic-years.store'), [
                'name' => '2026/2027',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.academic-years.index'));

        $this->assertFalse($previous->fresh()->is_active);
        $this->assertEquals(1, AcademicYear::where('is_active', true)->count());
    }

    /**
     * Test activating a year through update deactivates the other years.
     */
    public function test_updating_a_year_to_active_deactivates_other_years(): void
    {
        $active = AcademicYear::factory()->create(['is_active' => true]);
        $inactive = AcademicYear::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->put(route('admin.academic-years.update', $inactive), [
                'name' => $inactive->name,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.academic-years.index'));

        $this->assertFalse($active->fresh()->is_active);
        $this->assertTrue($inactive->fresh()->is_active);
    }

    /**
     * Test an academic year name must be unique.
     */
    public function test_academic_year_name_must_be_unique(): void
    {
        AcademicYear::factory()->create(['name' => '2026/2027']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.academic-years.store'), [
                'name' => '2026/2027',
            ]);

        $response->assertSessionHasErrors('name');
    }

    /**
     * Test an admin can update an academic year.
     */
    public function test_admin_can_update_academic_year(): void
    {
        $year = AcademicYear::factory()->create(['name' => '2025/2026']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.academic-years.update', $year), [
                'name' => '2026/2027',
            ]);

        $response->assertRedirect(route('admin.academic-years.index'));
        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'name' => '2026/2027']);
    }

    /**
     * Test an admin can delete an academic year.
     */
    public function test_admin_can_delete_academic_year(): void
    {
        $year = AcademicYear::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.academic-years.destroy', $year));

        $response->assertRedirect(route('admin.academic-years.index'));
        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
    }
}
