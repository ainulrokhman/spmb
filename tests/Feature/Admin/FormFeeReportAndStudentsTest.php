<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Paket B: the report page gains a form-fee payment recap
 * plus a form-payments CSV export, and the new Daftar Siswa
 * page lists only students who completed re-registration.
 */
class FormFeeReportAndStudentsTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->guardian = User::factory()->create(['phone' => '081200007777']);
        $this->guardian->assignRole('Wali');
    }

    /**
     * @return array{year: AcademicYear, unit: SchoolUnit, wave: AdmissionWave, track: AdmissionTrack}
     */
    private function scenario(): array
    {
        $year = AcademicYear::factory()->create(['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => 'SMP Uji']);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);

        return ['year' => $year, 'unit' => $unit, 'wave' => $wave, 'track' => $track];
    }

    private function application(array $scenario, array $overrides = []): Application
    {
        static $seq = 0;
        $seq++;

        return Application::factory()->create(array_merge([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $scenario['unit']->id,
            'admission_wave_id' => $scenario['wave']->id,
            'admission_track_id' => $scenario['track']->id,
            'child_name' => 'Anak '.$seq,
            'birth_place' => 'Jakarta',
            'birth_date' => '2016-05-01',
            'gender' => 'L',
            'registration_number' => '0326'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
        ], $overrides));
    }

    public function test_report_page_shows_the_form_fee_recap_with_channel_totals(): void
    {
        $scenario = $this->scenario();

        $manual = $this->application($scenario, ['form_fee_amount' => 75000, 'form_fee_status' => 'paid', 'status' => 'submitted']);
        $manual->payments()->create(['channel' => 'manual', 'amount' => 75000, 'status' => 'paid', 'reference' => 'FRM-MANUAL-1', 'paid_at' => now()]);

        $midtrans = $this->application($scenario, ['form_fee_amount' => 25000, 'form_fee_status' => 'paid', 'status' => 'submitted']);
        $midtrans->payments()->create(['channel' => 'midtrans', 'amount' => 25000, 'status' => 'paid', 'reference' => 'FRM-MID-1', 'paid_at' => now()]);

        $this->application($scenario, ['form_fee_status' => 'waived', 'status' => 'filling_form']);
        $this->application($scenario, ['form_fee_status' => 'free', 'status' => 'filling_form']);
        $this->application($scenario, ['form_fee_amount' => 50000, 'form_fee_status' => 'unpaid', 'status' => 'awaiting_form_fee']);

        $this->actingAs($this->admin())
            ->get(route('admin.reports.index', ['academic_year_id' => $scenario['year']->id]))
            ->assertOk()
            ->assertSee('Pembayaran Formulir')
            ->assertSee('Rp 100.000')
            ->assertSee('Manual')
            ->assertSee('Rp 75.000')
            ->assertSee('Midtrans')
            ->assertSee('Rp 25.000');
    }

    public function test_form_payments_export_lists_each_application_with_its_settled_payment(): void
    {
        $scenario = $this->scenario();

        $paid = $this->application($scenario, ['child_name' => 'Anak Lunas', 'form_fee_amount' => 75000, 'form_fee_status' => 'paid', 'status' => 'submitted']);
        $paid->payments()->create(['channel' => 'manual', 'amount' => 75000, 'status' => 'paid', 'reference' => 'FRM-MANUAL-1', 'paid_at' => now()]);
        $this->application($scenario, ['child_name' => 'Anak Belum', 'form_fee_amount' => 50000, 'form_fee_status' => 'unpaid', 'status' => 'awaiting_form_fee']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.reports.export.form-payments', ['academic_year_id' => $scenario['year']->id]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Anak Lunas', $csv);
        $this->assertStringContainsString('Manual', $csv);
        $this->assertStringContainsString('FRM-MANUAL-1', $csv);
        $this->assertStringContainsString('Anak Belum', $csv);
        $this->assertStringContainsString('Belum Bayar', $csv);
    }

    public function test_student_roster_lists_all_statuses_and_filters_by_status(): void
    {
        $scenario = $this->scenario();

        $student = $this->application($scenario, ['child_name' => 'Siswa Tuntas', 'status' => 're_registered']);
        Invoice::factory()->create(['application_id' => $student->id, 'status' => 'paid', 'paid_at' => now()]);
        $this->application($scenario, ['child_name' => 'Baru Lulus', 'status' => 'passed']);

        $this->actingAs($this->admin())
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('Daftar Siswa');

        // By default every status is listed (Paket C).
        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.students.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $this->assertSame(2, $response->json('recordsTotal'));

        // The status filter narrows the roster again.
        $filtered = $this->actingAs($this->admin())
            ->getJson(route('admin.students.index', ['status' => 're_registered']), ['X-Requested-With' => 'XMLHttpRequest']);

        $filtered->assertOk();
        $this->assertSame(1, $filtered->json('recordsTotal'));
        $this->assertStringContainsString('Siswa Tuntas', $filtered->json('data.0.student'));
        $this->assertStringContainsString('Daftar Ulang', $filtered->json('data.0.status_badge'));

        // The export follows the same rule: all by default,
        // narrowed when the status filter is given.
        $csv = $this->actingAs($this->admin())
            ->get(route('admin.students.export'))
            ->streamedContent();
        $this->assertStringContainsString('Siswa Tuntas', $csv);
        $this->assertStringContainsString('Baru Lulus', $csv);

        $csvFiltered = $this->actingAs($this->admin())
            ->get(route('admin.students.export', ['status' => 're_registered']))
            ->streamedContent();
        $this->assertStringContainsString('Siswa Tuntas', $csvFiltered);
        $this->assertStringNotContainsString('Baru Lulus', $csvFiltered);
    }

    public function test_student_roster_is_scoped_to_the_committee_units(): void
    {
        $scenario = $this->scenario();
        $otherUnit = SchoolUnit::factory()->create(['name' => 'SMK Lain']);
        $otherWave = AdmissionWave::factory()->create([
            'school_unit_id' => $otherUnit->id,
            'academic_year_id' => $scenario['year']->id,
        ]);
        $otherTrack = AdmissionTrack::factory()->create(['school_unit_id' => $otherUnit->id]);

        $this->application($scenario, ['child_name' => 'Siswa Sendiri', 'status' => 're_registered']);
        $this->application($scenario, [
            'child_name' => 'Siswa Unit Lain',
            'status' => 're_registered',
            'school_unit_id' => $otherUnit->id,
            'admission_wave_id' => $otherWave->id,
            'admission_track_id' => $otherTrack->id,
        ]);

        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');
        $panitia->schoolUnits()->sync([$scenario['unit']->id]);

        $response = $this->actingAs($panitia)
            ->getJson(route('admin.students.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertStringContainsString('Siswa Sendiri', $response->json('data.0.student'));
    }
}
