<?php

namespace Tests\Feature;

use App\Contracts\InvoiceServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Relief (keringanan) simplification and invoice installments:
 * guardians request with a reason only, the committee decides the
 * form and size of relief (or grants it without a request), and
 * installment plans are requested by guardians but detailed by
 * the committee.
 */
class ReliefInstallmentTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081234567890']);
        $this->guardian->assignRole('Wali');
    }

    private function invoices(): InvoiceServiceInterface
    {
        return app(InvoiceServiceInterface::class);
    }

    /**
     * A passed application (announced wave) on a fresh unit with a
     * single fixed fee of Rp1.000.000, plus its frozen invoice.
     *
     * @return array{application: Application, invoice: Invoice}
     */
    private function passedWithInvoice(string $unitCode): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Cicilan '.$unitCode, 'code' => $unitCode]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'announcement_at' => now()->subHour(),
        ]);
        $wave->admissionTracks()->attach($track);

        $component = FeeComponent::factory()->create(['code' => 'DU'.$unitCode, 'name' => 'Daftar Ulang']);
        $fee = Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $component->id, 'mode' => 'fixed']);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 1000000]);

        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $year->id,
            'status' => 'passed',
        ]);

        $invoice = $this->invoices()->createInvoice($application, []);
        $this->assertSame(1000000, $invoice->total);

        return ['application' => $application, 'invoice' => $invoice];
    }

    public function test_guardian_relief_request_stores_reason_only(): void
    {
        $application = Application::factory()->awaitingFee(50000)->create(['user_id' => $this->guardian->id]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('pending', $application->form_fee_request_status);
        $this->assertSame('Anak yatim', $application->form_fee_request_reason);
        $this->assertNull($application->form_fee_request_kind);
        $this->assertNull($application->form_fee_request_type);
        $this->assertNull($application->form_fee_request_value);
    }

    public function test_committee_grants_form_fee_relief_without_any_request(): void
    {
        $partial = Application::factory()->awaitingFee(50000)->create();
        $full = Application::factory()->awaitingFee(50000)->create();

        $this->actingAs($this->superAdmin())
            ->post(route('admin.form-payments.grant', $partial), ['relief_type' => 'percent', 'relief_value' => 50, 'note' => 'Anak karyawan'])
            ->assertSessionHas('success');

        $partial->refresh();
        $this->assertSame(25000, $partial->payableAmount());
        $this->assertSame('unpaid', $partial->form_fee_status);
        $this->assertNotNull($partial->form_fee_decided_by);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.form-payments.grant', $full), ['relief_type' => 'waiver'])
            ->assertSessionHas('success');

        $full->refresh();
        $this->assertSame('waived', $full->form_fee_status);
        $this->assertSame('filling_form', $full->status);
    }

    public function test_committee_grants_invoice_relief_without_any_request(): void
    {
        ['invoice' => $invoice] = $this->passedWithInvoice('51');

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.grant-discount', $invoice), ['relief_type' => 'nominal', 'relief_value' => 200000])
            ->assertSessionHas('success');

        $this->assertSame(800000, $invoice->fresh()->payableAmount());

        ['application' => $application2, 'invoice' => $invoice2] = $this->passedWithInvoice('52');

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.grant-discount', $invoice2), ['relief_type' => 'waiver'])
            ->assertSessionHas('success');

        $this->assertSame('paid', $invoice2->fresh()->status);
        $this->assertSame('re_registered', $application2->fresh()->status);
    }

    public function test_installment_full_flow_until_re_registered(): void
    {
        ['application' => $application, 'invoice' => $invoice] = $this->passedWithInvoice('53');

        // Guardian requests installments with a reason only.
        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-installment', $application), ['reason' => 'Dibayar mengikuti gaji bulanan'])
            ->assertSessionHas('success');
        $this->assertSame('pending', $invoice->fresh()->installment_request_status);

        // Committee approves and details two terms.
        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.decide-installment', $invoice), [
                'decision' => 'approve',
                'installments' => [
                    ['amount' => 600000, 'due_date' => now()->addDays(7)->toDateString()],
                    ['amount' => 400000, 'due_date' => now()->addDays(37)->toDateString()],
                ],
            ])
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('approved', $invoice->installment_request_status);
        $this->assertCount(2, $invoice->installments);

        // Pay both terms one by one through the manual channel.
        foreach ($invoice->installments as $installment) {
            $this->actingAs($this->guardian)
                ->post(route('guardian.invoices.pay-installment', [$application, $installment]), ['channel' => 'manual'])
                ->assertSessionHas('success');

            $payment = $installment->payments()->latest('id')->firstOrFail();

            $this->actingAs($this->guardian)
                ->post(route('guardian.invoices.declare-installment-paid', [$application, $payment]))
                ->assertSessionHas('success');

            $this->actingAs($this->superAdmin())
                ->post(route('admin.invoices.confirm', $payment))
                ->assertSessionHas('success');

            $this->assertSame('paid', $installment->fresh()->status);
        }

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1000000, $invoice->paidAmount());
        $this->assertSame(0, $invoice->remainingAmount());
        $this->assertSame('re_registered', $application->fresh()->status);
    }

    public function test_installment_schedule_total_must_match_payable(): void
    {
        ['application' => $application, 'invoice' => $invoice] = $this->passedWithInvoice('54');

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-installment', $application), ['reason' => 'Minta cicilan'])
            ->assertSessionHas('success');

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.decide-installment', $invoice), [
                'decision' => 'approve',
                'installments' => [
                    ['amount' => 500000, 'due_date' => now()->addDays(7)->toDateString()],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $invoice->fresh()->installments()->count());
        $this->assertSame('pending', $invoice->fresh()->installment_request_status);
    }

    public function test_relief_and_installment_requests_block_each_other(): void
    {
        ['application' => $application, 'invoice' => $invoice] = $this->passedWithInvoice('55');

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-discount', $application), ['reason' => 'Minta keringanan'])
            ->assertSessionHas('success');

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-installment', $application), ['reason' => 'Minta cicilan'])
            ->assertSessionHas('error');

        $this->assertNull($invoice->fresh()->installment_request_status);
    }

    public function test_relief_grant_blocked_once_installment_plan_exists(): void
    {
        ['invoice' => $invoice] = $this->passedWithInvoice('56');

        $installments = app(\App\Contracts\InstallmentServiceInterface::class);
        $installments->requestInstallment($invoice, 'Minta cicilan');
        $installments->decideInstallmentRequest($invoice->fresh(), true, [
            ['amount' => 1000000, 'due_date' => now()->addDays(7)->toDateString()],
        ], $this->superAdmin(), null);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.grant-discount', $invoice->fresh()), ['relief_type' => 'percent', 'relief_value' => 10])
            ->assertSessionHas('error');

        $this->assertSame(0, $invoice->fresh()->discountAmount());
    }

    public function test_revise_schedule_keeps_total_and_locks_paid_terms(): void
    {
        ['invoice' => $invoice] = $this->passedWithInvoice('57');

        $installments = app(\App\Contracts\InstallmentServiceInterface::class);
        $installments->requestInstallment($invoice, 'Minta cicilan');
        $installments->decideInstallmentRequest($invoice->fresh(), true, [
            ['amount' => 600000, 'due_date' => now()->addDays(7)->toDateString()],
            ['amount' => 400000, 'due_date' => now()->addDays(37)->toDateString()],
        ], $this->superAdmin(), null);

        [$first, $second] = $invoice->fresh()->installments;

        // Rebalance the two unpaid terms.
        $installments->reviseSchedule($invoice->fresh(), [
            $first->id => ['amount' => 500000, 'due_date' => now()->addDays(10)->toDateString()],
            $second->id => ['amount' => 500000, 'due_date' => now()->addDays(40)->toDateString()],
        ]);
        $this->assertSame(500000, $first->fresh()->amount);

        // Settle the first term, then it can no longer be revised.
        $payments = app(\App\Services\PaymentService::class);
        $result = $payments->startInstallmentPayment($first->fresh(), 'manual');
        $payments->declareManualPaid($result['payment']);
        $payments->confirmPayment($result['payment']->fresh());
        $this->assertSame('paid', $first->fresh()->status);

        try {
            $installments->reviseSchedule($invoice->fresh(), [
                $first->id => ['amount' => 500000, 'due_date' => now()->addDays(10)->toDateString()],
                $second->id => ['amount' => 500000, 'due_date' => now()->addDays(40)->toDateString()],
            ]);
            $this->fail('Paid term revision should have been rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah lunas', $e->getMessage());
        }
    }
}
