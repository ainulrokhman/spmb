<?php

namespace Tests\Feature\Guardian;

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
 * The guardian dashboard (Paket D): every child shows its
 * journey as steps — done, the guardian's turn, waiting on
 * the committee, or not yet reached — and everything that
 * needs the guardian is collected into one action list on
 * top, so a guardian on a phone sees in one glance what is
 * done, what is in progress and what is still missing.
 */
class GuardianDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private SchoolUnit $unit;

    private AdmissionWave $wave;

    private AdmissionTrack $track;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081200007777']);
        $this->guardian->assignRole('Wali');

        $this->unit = SchoolUnit::factory()->create(['name' => 'SMP Dasbor']);
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $this->wave = AdmissionWave::factory()->create([
            'school_unit_id' => $this->unit->id,
            'academic_year_id' => $year->id,
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->addDays(20),
            'announcement_at' => now()->addDays(10),
        ]);
        $this->track = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id]);
    }

    private function makeApplication(array $attributes = []): Application
    {
        return Application::factory()->create(array_merge([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $this->unit->id,
            'admission_wave_id' => $this->wave->id,
            'admission_track_id' => $this->track->id,
            'child_name' => 'Anak Dasbor',
            'status' => 'awaiting_form_fee',
            'form_fee_amount' => 50000,
            'form_fee_status' => 'unpaid',
        ], $attributes));
    }

    /**
     * @return array<string, string> step key => state
     */
    private function stepStates($response, Application $application): array
    {
        $steps = collect($response->viewData('steps')[$application->id]);

        return $steps->mapWithKeys(fn (array $step) => [$step['key'] => $step['state']])->all();
    }

    /**
     * @return list<string>
     */
    private function actionLabels($response): array
    {
        return collect($response->viewData('actions'))->pluck('label')->all();
    }

    public function test_fee_stage_asks_for_payment_and_lists_all_steps(): void
    {
        $application = $this->makeApplication();

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        $response->assertSee('Perlu Tindakan Anda')
            ->assertSee('Bayar Formulir')
            ->assertSee('Isi Formulir')
            ->assertSee('Unggah Berkas')
            ->assertSee('Verifikasi Panitia')
            ->assertSee('Pengumuman Hasil')
            ->assertSee('Daftar Ulang')
            ->assertSee('Rp 50.000');

        $this->assertSame(['Bayar Formulir'], $this->actionLabels($response));
        $this->assertSame([
            'form_fee' => 'current',
            'form' => 'todo',
            'documents' => 'todo',
            'verification' => 'todo',
            'announcement' => 'todo',
            're_registration' => 'todo',
        ], $this->stepStates($response, $application));
    }

    public function test_pending_relief_and_declared_payment_wait_for_the_committee(): void
    {
        $relief = $this->makeApplication(['form_fee_request_status' => 'pending', 'form_fee_request_reason' => 'Mohon keringanan']);
        $declared = $this->makeApplication(['child_name' => 'Anak Kedua', 'form_fee_status' => 'awaiting_confirmation']);

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        // Nothing here is the guardian's move right now.
        $response->assertDontSee('Perlu Tindakan Anda');
        $this->assertSame([], $this->actionLabels($response));
        $this->assertSame('waiting', $this->stepStates($response, $relief)['form_fee']);
        $this->assertSame('waiting', $this->stepStates($response, $declared)['form_fee']);
    }

    public function test_form_stage_moves_the_action_to_the_form(): void
    {
        $application = $this->makeApplication(['status' => 'filling_form', 'form_fee_status' => 'paid']);

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        $this->assertSame(['Isi Formulir'], $this->actionLabels($response));
        $states = $this->stepStates($response, $application);
        $this->assertSame('done', $states['form_fee']);
        $this->assertSame('current', $states['form']);
        $this->assertSame('todo', $states['documents']);
    }

    public function test_submitted_application_waits_for_verification(): void
    {
        $application = $this->makeApplication(['status' => 'submitted', 'form_fee_status' => 'paid']);

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        $response->assertSee('Menunggu Panitia');
        $this->assertSame([], $this->actionLabels($response));
        $states = $this->stepStates($response, $application);
        $this->assertSame('done', $states['documents']);
        $this->assertSame('waiting', $states['verification']);
    }

    public function test_needs_revision_asks_the_guardian_to_fix_the_application(): void
    {
        $application = $this->makeApplication([
            'status' => 'needs_revision',
            'form_fee_status' => 'paid',
            'verification_note' => 'Foto akta kelahiran buram, mohon unggah ulang.',
        ]);

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        $response->assertSee('Perbaiki Pendaftaran')
            ->assertSee('Foto akta kelahiran buram, mohon unggah ulang.');
        $this->assertSame(['Perbaiki Pendaftaran'], $this->actionLabels($response));
        $this->assertSame('attention', $this->stepStates($response, $application)['verification']);
    }

    public function test_result_stays_hidden_until_announcement_then_asks_for_re_registration(): void
    {
        $application = $this->makeApplication(['status' => 'passed', 'form_fee_status' => 'paid']);
        Invoice::factory()->create(['application_id' => $application->id, 'total' => 750000, 'status' => 'unpaid']);

        $hidden = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();
        $hidden->assertSee('Menunggu Pengumuman')
            ->assertDontSee('Lulus!');
        $this->assertSame([], $this->actionLabels($hidden));
        $this->assertSame('waiting', $this->stepStates($hidden, $application)['announcement']);

        $this->wave->update(['announcement_at' => now()->subHour()]);

        $announced = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();
        $announced->assertSee('Lulus! Selamat');
        $this->assertSame(['Bayar Tagihan'], $this->actionLabels($announced));
        $states = $this->stepStates($announced, $application);
        $this->assertSame('done', $states['announcement']);
        $this->assertSame('current', $states['re_registration']);
    }

    public function test_next_unpaid_installment_term_is_the_dashboard_action(): void
    {
        $this->wave->update(['announcement_at' => now()->subHour()]);
        $application = $this->makeApplication(['status' => 'passed', 'form_fee_status' => 'paid']);
        $invoice = Invoice::factory()->create(['application_id' => $application->id, 'total' => 600000, 'status' => 'unpaid']);
        $invoice->installments()->create(['sequence' => 1, 'amount' => 300000, 'due_date' => now()->subDays(5), 'status' => 'paid', 'paid_at' => now()]);
        $invoice->installments()->create(['sequence' => 2, 'amount' => 300000, 'due_date' => now()->addDays(3), 'status' => 'unpaid']);

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        $response->assertSee('Bayar Termin')->assertSee('Termin ke-2');
        $this->assertSame(['Bayar Termin'], $this->actionLabels($response));
    }

    public function test_re_registered_child_is_fully_done(): void
    {
        $application = $this->makeApplication(['status' => 're_registered', 'form_fee_status' => 'paid']);
        Invoice::factory()->create(['application_id' => $application->id, 'total' => 750000, 'status' => 'paid']);

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();

        $response->assertSee('Lihat Daftar Ulang');
        $this->assertSame([], $this->actionLabels($response));
        $this->assertSame([
            'form_fee' => 'done',
            'form' => 'done',
            'documents' => 'done',
            'verification' => 'done',
            'announcement' => 'done',
            're_registration' => 'done',
        ], $this->stepStates($response, $application));
    }
}
