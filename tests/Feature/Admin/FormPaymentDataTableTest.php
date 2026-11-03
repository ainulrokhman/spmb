<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Paket C: the form payments page is one DataTable listing
 * every application in every form-fee condition, with
 * per-row actions — confirm an awaiting manual payment,
 * decide or grant relief (through the page's shared modal),
 * view the proof, open the applicant detail.
 */
class FormPaymentDataTableTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private SchoolUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->guardian = User::factory()->create(['phone' => '081200004444']);
        $this->guardian->assignRole('Wali');
        $this->unit = SchoolUnit::factory()->create(['name' => 'SMP Bayar']);
    }

    private function application(array $overrides = []): Application
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $this->unit->id,
            'academic_year_id' => $year->id,
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id]);

        return Application::factory()->create(array_merge([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $this->unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'child_name' => 'Anak Uji',
        ], $overrides));
    }

    /**
     * One application per form-fee condition.
     *
     * @return array<string, Application>
     */
    private function seedAllConditions(): array
    {
        $grantable = $this->application(['child_name' => 'Anak Belum Bayar', 'form_fee_amount' => 50000, 'form_fee_status' => 'unpaid', 'status' => 'awaiting_form_fee']);

        $paid = $this->application(['child_name' => 'Anak Lunas', 'form_fee_amount' => 50000, 'form_fee_status' => 'paid', 'status' => 'submitted']);
        $paid->payments()->create(['channel' => 'manual', 'amount' => 50000, 'status' => 'paid', 'reference' => 'FRM-LUNAS-1', 'paid_at' => now()]);

        $awaiting = $this->application(['child_name' => 'Anak Menunggu', 'form_fee_amount' => 50000, 'form_fee_status' => 'awaiting_confirmation', 'status' => 'awaiting_form_fee']);
        $awaiting->payments()->create(['channel' => 'manual', 'amount' => 50000, 'status' => 'awaiting_confirmation', 'reference' => 'FRM-TUNGGU-1', 'proof_path' => 'bukti-bayar/contoh.jpg']);

        $pending = $this->application(['child_name' => 'Anak Mengajukan', 'form_fee_amount' => 50000, 'form_fee_status' => 'unpaid', 'status' => 'awaiting_form_fee',
            'form_fee_request_status' => 'pending', 'form_fee_request_reason' => 'Anak yatim']);

        $waived = $this->application(['child_name' => 'Anak Bebas', 'form_fee_amount' => 50000, 'form_fee_status' => 'waived', 'status' => 'filling_form']);

        return ['grantable' => $grantable, 'paid' => $paid, 'awaiting' => $awaiting, 'pending' => $pending, 'waived' => $waived];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, string>
     */
    private function rowFor(array $rows, string $childName): array
    {
        foreach ($rows as $row) {
            if (str_contains($row['applicant'], $childName)) {
                return $row;
            }
        }

        $this->fail("Row for {$childName} not found.");
    }

    public function test_datatable_lists_all_fee_conditions_with_state_actions(): void
    {
        $apps = $this->seedAllConditions();

        $this->actingAs($this->admin())
            ->get(route('admin.form-payments.index'))
            ->assertOk()
            ->assertSee('form-payments-table')
            ->assertSee('relief-modal')
            ->assertSee('Semua Status Biaya');

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.form-payments.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $this->assertSame(5, $response->json('recordsTotal'));
        $rows = $response->json('data');

        // Awaiting confirmation: ACC button + proof link.
        $awaitingRow = $this->rowFor($rows, 'Anak Menunggu');
        $awaitingPayment = $apps['awaiting']->payments()->firstOrFail();
        $this->assertStringContainsString('Konfirmasi', $awaitingRow['actions']);
        $this->assertStringContainsString('/form-payments/payments/'.$awaitingPayment->id.'/confirm', $awaitingRow['actions']);
        $this->assertStringContainsString('/payments/'.$awaitingPayment->id.'/proof', $awaitingRow['actions']);
        $this->assertStringContainsString('Menunggu Konfirmasi', $awaitingRow['fee_status']);

        // Pending request: the modal opens in decide mode and
        // carries the guardian's reason.
        $pendingRow = $this->rowFor($rows, 'Anak Mengajukan');
        $this->assertStringContainsString('data-mode="decide"', $pendingRow['actions']);
        $this->assertStringContainsString('Anak yatim', $pendingRow['actions']);
        $this->assertStringContainsString('Pengajuan keringanan menunggu', $pendingRow['fee_status']);

        // Still unpaid without a request: grant mode.
        $grantableRow = $this->rowFor($rows, 'Anak Belum Bayar');
        $this->assertStringContainsString('data-mode="grant"', $grantableRow['actions']);

        // Settled rows carry no work actions, only the detail.
        $paidRow = $this->rowFor($rows, 'Anak Lunas');
        $this->assertStringContainsString('Lunas', $paidRow['fee_status']);
        $this->assertStringContainsString('Manual', $paidRow['payment_info']);
        $this->assertStringNotContainsString('relief-action', $paidRow['actions']);
        $this->assertStringNotContainsString('Konfirmasi', $paidRow['actions']);

        $waivedRow = $this->rowFor($rows, 'Anak Bebas');
        $this->assertStringContainsString('Dibebaskan', $waivedRow['fee_status']);
    }

    public function test_datatable_filters_by_fee_status_and_channel(): void
    {
        $this->seedAllConditions();

        $byStatus = $this->actingAs($this->admin())
            ->getJson(route('admin.form-payments.index', ['form_fee_status' => 'paid']), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(1, $byStatus->json('recordsTotal'));
        $this->assertStringContainsString('Anak Lunas', $byStatus->json('data.0.applicant'));

        // The channel filter matches settled payments only.
        $byChannel = $this->actingAs($this->admin())
            ->getJson(route('admin.form-payments.index', ['channel' => 'manual']), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(1, $byChannel->json('recordsTotal'));

        $none = $this->actingAs($this->admin())
            ->getJson(route('admin.form-payments.index', ['channel' => 'midtrans']), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(0, $none->json('recordsTotal'));
    }

    public function test_datatable_is_scoped_to_committee_units(): void
    {
        $this->seedAllConditions();

        $otherUnit = SchoolUnit::factory()->create(['name' => 'SMK Lain']);
        $outsider = User::factory()->create();
        $outsider->assignRole('Panitia');
        $outsider->schoolUnits()->sync([$otherUnit->id]);

        $response = $this->actingAs($outsider)
            ->getJson(route('admin.form-payments.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(0, $response->json('recordsTotal'));

        $insider = User::factory()->create();
        $insider->assignRole('Panitia');
        $insider->schoolUnits()->sync([$this->unit->id]);

        $response = $this->actingAs($insider)
            ->getJson(route('admin.form-payments.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(5, $response->json('recordsTotal'));

        // A committee member without the deciding permission
        // sees the list but no work actions.
        $lister = User::factory()->create();
        $lister->assignRole('User');
        $lister->givePermissionTo('form-payment-list');
        $lister->schoolUnits()->sync([$this->unit->id]);

        $response = $this->actingAs($lister)
            ->getJson(route('admin.form-payments.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertSame(5, $response->json('recordsTotal'));
        $this->assertStringNotContainsString('relief-action', $response->json('data.0.actions'));
        $this->assertStringNotContainsString('Konfirmasi', $response->json('data.0.actions'));
    }
}
