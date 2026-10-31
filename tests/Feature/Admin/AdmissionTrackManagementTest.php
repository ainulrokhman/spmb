<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTrack;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class AdmissionTrackManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.admission-tracks.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.admission-tracks.index';
    }

    /**
     * Test guest is redirected to login from the admission track list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the admission-track-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_admission_track_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the admission track list.
     */
    public function test_admin_can_view_admission_track_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create an admission track for a unit.
     */
    public function test_admin_can_create_admission_track(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => $unit->id,
                'name' => 'Prestasi',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.admission-tracks.index'));
        $this->assertDatabaseHas('admission_tracks', ['school_unit_id' => $unit->id, 'name' => 'Prestasi']);
    }

    /**
     * Test a track name must be unique within its unit but may repeat across units.
     */
    public function test_track_name_is_unique_per_unit_only(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();
        AdmissionTrack::factory()->create(['school_unit_id' => $unitA->id, 'name' => 'Reguler']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => $unitA->id,
                'name' => 'Reguler',
            ]);
        $response->assertSessionHasErrors('name');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => $unitB->id,
                'name' => 'Reguler',
            ]);
        $response->assertRedirect(route('admin.admission-tracks.index'));
    }

    /**
     * Test a track requires an existing school unit.
     */
    public function test_track_requires_an_existing_school_unit(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => 999,
                'name' => 'Prestasi',
            ]);

        $response->assertSessionHasErrors('school_unit_id');
    }

    /**
     * Test an admin can update an admission track.
     */
    public function test_admin_can_update_admission_track(): void
    {
        $track = AdmissionTrack::factory()->create(['name' => 'Reguler']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.admission-tracks.update', $track), [
                'school_unit_id' => $track->school_unit_id,
                'name' => 'Reguler Gelombang Khusus',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.admission-tracks.index'));
        $this->assertDatabaseHas('admission_tracks', ['id' => $track->id, 'name' => 'Reguler Gelombang Khusus']);
    }

    /**
     * Test an admin can delete an admission track.
     */
    public function test_admin_can_delete_admission_track(): void
    {
        $track = AdmissionTrack::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.admission-tracks.destroy', $track));

        $response->assertRedirect(route('admin.admission-tracks.index'));
        $this->assertDatabaseMissing('admission_tracks', ['id' => $track->id]);
    }

    /**
     * Test deleting a school unit also deletes its admission tracks.
     */
    public function test_deleting_a_unit_cascades_to_its_tracks(): void
    {
        $unit = SchoolUnit::factory()->create();
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);

        $unit->delete();

        $this->assertDatabaseMissing('admission_tracks', ['id' => $track->id]);
    }
}
