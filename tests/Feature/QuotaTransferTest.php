<?php

namespace Tests\Feature;

use App\Contracts\ApplicationServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Major;
use App\Models\SchoolUnit;
use App\Models\UnitQuota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Tiered quotas (unit per year, major total and per gender)
 * enforced at submit time, plus the transfer (pindahan)
 * track: two intakes in different academic years run side by
 * side without eating each other's quota, and transfer
 * applicants must name their target grade.
 */
class QuotaTransferTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private SchoolUnit $unit;

    private AcademicYear $yearNow;

    private AcademicYear $yearNext;

    private AdmissionWave $waveNow;

    private AdmissionWave $waveNext;

    private AdmissionTrack $trackRegular;

    private AdmissionTrack $trackTransfer;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081200007777']);
        $this->guardian->assignRole('Wali');

        $this->yearNow = AcademicYear::factory()->create(['name' => '2025/2026', 'is_active' => true]);
        $this->yearNext = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => false]);

        $this->unit = SchoolUnit::factory()->create(['name' => 'SMP Kuota', 'code' => '51', 'form_fee' => 0]);

        $this->waveNow = AdmissionWave::factory()->create([
            'school_unit_id' => $this->unit->id,
            'academic_year_id' => $this->yearNow->id,
            'name' => 'Pindahan 2025',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(20),
        ]);
        $this->waveNext = AdmissionWave::factory()->create([
            'school_unit_id' => $this->unit->id,
            'academic_year_id' => $this->yearNext->id,
            'name' => 'Gelombang 1 2026',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(20),
        ]);

        $this->trackRegular = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id, 'name' => 'Reguler', 'is_transfer' => false]);
        $this->trackTransfer = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id, 'name' => 'Pindahan', 'is_transfer' => true]);
        $this->waveNow->admissionTracks()->attach($this->trackTransfer->id);
        $this->waveNext->admissionTracks()->attach($this->trackRegular->id);

        $this->major = Major::factory()->create(['school_unit_id' => $this->unit->id, 'name' => 'Tahfidz']);
    }

    /** An application ready to submit, optionally already registered. */
    private function candidate(array $overrides = []): Application
    {
        return Application::factory()->create(array_merge([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $this->unit->id,
            'admission_wave_id' => $this->waveNext->id,
            'admission_track_id' => $this->trackRegular->id,
            'academic_year_id' => $this->yearNext->id,
            'status' => 'uploading_documents',
            'registration_number' => null,
            'gender' => 'L',
            'form_fee_status' => 'free',
        ], $overrides));
    }

    private function submit(Application $application): Application
    {
        return app(ApplicationServiceInterface::class)->submit($application);
    }

    public function test_unit_quota_blocks_submit_when_year_full(): void
    {
        UnitQuota::create(['school_unit_id' => $this->unit->id, 'academic_year_id' => $this->yearNext->id, 'quota' => 1]);
        $this->candidate(['registration_number' => '5126001', 'status' => 'submitted']);

        $blocked = $this->candidate();
        try {
            $this->submit($blocked);
            $this->fail('Submit seharusnya ditolak kuota unit.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Kuota unit', $e->getMessage());
        }

        // The other academic year's intake is a separate pool.
        $otherYear = $this->candidate([
            'admission_wave_id' => $this->waveNow->id,
            'admission_track_id' => $this->trackTransfer->id,
            'academic_year_id' => $this->yearNow->id,
        ]);
        $this->submit($otherYear);
        $this->assertNotNull($otherYear->refresh()->registration_number);
    }

    public function test_major_total_quota(): void
    {
        $this->major->update(['quota' => 1]);
        $this->candidate(['registration_number' => '5126001', 'status' => 'submitted', 'major_id' => $this->major->id]);

        $blocked = $this->candidate(['major_id' => $this->major->id]);
        try {
            $this->submit($blocked);
            $this->fail('Submit seharusnya ditolak kuota program.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Kuota program Tahfidz sudah penuh.', $e->getMessage());
        }

        $noMajor = $this->candidate(['major_id' => null]);
        $this->submit($noMajor);
        $this->assertNotNull($noMajor->refresh()->registration_number);
    }

    public function test_major_gender_quotas_are_separate(): void
    {
        $this->major->update(['quota_putra' => 1, 'quota_putri' => 1]);
        $this->candidate(['registration_number' => '5126001', 'status' => 'submitted', 'major_id' => $this->major->id, 'gender' => 'L']);

        $boy = $this->candidate(['major_id' => $this->major->id, 'gender' => 'L']);
        try {
            $this->submit($boy);
            $this->fail('Submit seharusnya ditolak kuota putra.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('untuk putra sudah penuh.', $e->getMessage());
        }

        $girl = $this->candidate(['major_id' => $this->major->id, 'gender' => 'P']);
        $this->submit($girl);
        $this->assertNotNull($girl->refresh()->registration_number);
    }

    public function test_resubmission_is_exempt_from_quotas(): void
    {
        UnitQuota::create(['school_unit_id' => $this->unit->id, 'academic_year_id' => $this->yearNext->id, 'quota' => 1]);
        $this->candidate(['registration_number' => '5126001', 'status' => 'submitted']);

        $revision = $this->candidate(['registration_number' => '5126002', 'status' => 'needs_revision']);
        $this->submit($revision);
        $this->assertSame('submitted', $revision->refresh()->status);
        $this->assertSame('5126002', $revision->registration_number);
    }

    public function test_start_form_lists_both_years_waves(): void
    {
        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.create', ['unit' => $this->unit->id]))
            ->assertOk()
            ->assertSee('Pindahan 2025')
            ->assertSee('Gelombang 1 2026');
    }

    public function test_transfer_track_requires_and_stores_target_grade(): void
    {
        $payload = [
            'child_name' => 'Anak Pindahan',
            'guardian_relation' => 'ayah',
            'school_unit_id' => $this->unit->id,
            'admission_wave_id' => $this->waveNow->id,
            'admission_track_id' => $this->trackTransfer->id,
        ];

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), $payload)
            ->assertSessionHasErrors('target_grade');
        $this->assertSame(0, Application::count());

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), $payload + ['target_grade' => 8])
            ->assertRedirect();
        $application = Application::sole();
        $this->assertSame(8, $application->target_grade);
        $this->assertSame($this->yearNow->id, $application->academic_year_id);
    }

    public function test_regular_track_ignores_target_grade(): void
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), [
                'child_name' => 'Anak Reguler',
                'guardian_relation' => 'ibu',
                'school_unit_id' => $this->unit->id,
                'admission_wave_id' => $this->waveNext->id,
                'admission_track_id' => $this->trackRegular->id,
                'target_grade' => 3,
            ])
            ->assertRedirect();

        $this->assertNull(Application::sole()->target_grade);
    }

    public function test_admin_can_set_major_quota_track_flag_and_unit_quota(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put(route('admin.majors.update', $this->major), [
            'school_unit_id' => $this->unit->id,
            'name' => 'Tahfidz',
            'quota' => 25,
            'quota_putra' => 12,
            'quota_putri' => 13,
        ])->assertRedirect();
        $this->major->refresh();
        $this->assertSame([25, 12, 13], [$this->major->quota, $this->major->quota_putra, $this->major->quota_putri]);

        $this->actingAs($admin)->put(route('admin.admission-tracks.update', $this->trackRegular), [
            'school_unit_id' => $this->unit->id,
            'name' => 'Reguler',
            'is_transfer' => '1',
        ])->assertRedirect();
        $this->assertTrue($this->trackRegular->refresh()->is_transfer);

        $unitData = [
            'name' => $this->unit->name,
            'code' => $this->unit->code,
            'level' => $this->unit->level,
            'program_id' => $this->unit->program_id,
            'unit_quotas' => [$this->yearNext->id => 100],
        ];
        $this->actingAs($admin)->put(route('admin.school-units.update', $this->unit), $unitData)->assertRedirect();
        $this->assertSame(100, UnitQuota::where('school_unit_id', $this->unit->id)->where('academic_year_id', $this->yearNext->id)->sole()->quota);

        $unitData['unit_quotas'] = [$this->yearNext->id => null];
        $this->actingAs($admin)->put(route('admin.school-units.update', $this->unit), $unitData)->assertRedirect();
        $this->assertSame(0, UnitQuota::count());
    }
}
