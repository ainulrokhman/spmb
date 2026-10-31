<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class AdmissionWaveManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.admission-waves.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.admission-waves.index';
    }

    /**
     * A valid admission wave payload for the given unit and year.
     */
    private function validWavePayload(SchoolUnit $unit, AcademicYear $year, array $overrides = []): array
    {
        return array_merge([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Gelombang 1',
            'quota' => 100,
            'opens_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'closes_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * Test guest is redirected to login from the admission wave list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the admission-wave-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_admission_wave_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the admission wave list.
     */
    public function test_admin_can_view_admission_wave_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create a wave opening tracks of its own unit.
     */
    public function test_admin_can_create_wave_with_own_unit_tracks(): void
    {
        $unit = SchoolUnit::factory()->create();
        $year = AcademicYear::factory()->create();
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-waves.store'), $this->validWavePayload($unit, $year, [
                'tracks' => [$track->id],
            ]));

        $response->assertRedirect(route('admin.admission-waves.index'));
        $wave = AdmissionWave::firstOrFail();
        $this->assertTrue($wave->admissionTracks->contains($track));
    }

    /**
     * Test a wave cannot open a track that belongs to another unit.
     */
    public function test_wave_cannot_open_track_from_another_unit(): void
    {
        $unit = SchoolUnit::factory()->create();
        $otherUnit = SchoolUnit::factory()->create();
        $year = AcademicYear::factory()->create();
        $foreignTrack = AdmissionTrack::factory()->create(['school_unit_id' => $otherUnit->id]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-waves.store'), $this->validWavePayload($unit, $year, [
                'tracks' => [$foreignTrack->id],
            ]));

        $response->assertSessionHasErrors('tracks.0');
        $this->assertDatabaseCount('admission_waves', 0);
    }

    /**
     * Test the closing time may not be before the opening time.
     */
    public function test_wave_closes_at_must_not_be_before_opens_at(): void
    {
        $unit = SchoolUnit::factory()->create();
        $year = AcademicYear::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.admission-waves.store'), $this->validWavePayload($unit, $year, [
                'opens_at' => now()->addMonth()->format('Y-m-d H:i:s'),
                'closes_at' => now()->subDay()->format('Y-m-d H:i:s'),
            ]));

        $response->assertSessionHasErrors('closes_at');
    }

    /**
     * Test the wave status follows the manual switch and the schedule.
     */
    public function test_wave_status_follows_switch_and_schedule(): void
    {
        $open = AdmissionWave::factory()->create([
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $upcoming = AdmissionWave::factory()->create([
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(10),
            'is_active' => true,
        ]);
        $closed = AdmissionWave::factory()->create([
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $switchedOff = AdmissionWave::factory()->create([
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'is_active' => false,
        ]);

        $this->assertSame(AdmissionWave::STATUS_OPEN, $open->status());
        $this->assertTrue($open->isCurrentlyOpen());
        $this->assertSame(AdmissionWave::STATUS_UPCOMING, $upcoming->status());
        $this->assertFalse($upcoming->isCurrentlyOpen());
        $this->assertSame(AdmissionWave::STATUS_CLOSED, $closed->status());
        $this->assertSame(AdmissionWave::STATUS_INACTIVE, $switchedOff->status());
        $this->assertFalse($switchedOff->isCurrentlyOpen());
    }

    /**
     * Test an admin can update a wave and resync its tracks.
     */
    public function test_admin_can_update_wave(): void
    {
        $unit = SchoolUnit::factory()->create();
        $trackA = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $trackB = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create(['school_unit_id' => $unit->id]);
        $wave->admissionTracks()->sync([$trackA->id]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.admission-waves.update', $wave), $this->validWavePayload($unit, $wave->academicYear, [
                'name' => 'Gelombang 1 Diperpanjang',
                'tracks' => [$trackB->id],
            ]));

        $response->assertRedirect(route('admin.admission-waves.index'));
        $this->assertDatabaseHas('admission_waves', ['id' => $wave->id, 'name' => 'Gelombang 1 Diperpanjang']);
        $this->assertEquals([$trackB->id], $wave->fresh()->admissionTracks->pluck('id')->all());
    }

    /**
     * Test an admin can delete an admission wave.
     */
    public function test_admin_can_delete_wave(): void
    {
        $wave = AdmissionWave::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.admission-waves.destroy', $wave));

        $response->assertRedirect(route('admin.admission-waves.index'));
        $this->assertDatabaseMissing('admission_waves', ['id' => $wave->id]);
    }
}
