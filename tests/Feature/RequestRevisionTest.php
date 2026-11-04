<?php

namespace Tests\Feature;

use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Three-way request decisions (PR A): besides approve and
 * reject, the committee may hand a relief or installment
 * request back for REVISION — the guardian fixes the same
 * request and resubmits it — while a rejection stays final
 * for that request but never blocks a brand-new one. While
 * a request awaits revision it still counts as the one
 * active request: the other kind and direct grants stay
 * locked.
 */
class RequestRevisionTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->guardian = User::factory()->create(['phone' => '081200008888']);
        $this->guardian->assignRole('Wali');
    }

    private function feeApplication(): Application
    {
        $unit = SchoolUnit::factory()->create();

        return Application::factory()->awaitingFee(50000)->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
        ]);
    }

    private function passedWithInvoice(): array
    {
        $unit = SchoolUnit::factory()->create();
        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'child_name' => 'Anak Revisi',
            'status' => 'passed',
            'form_fee_amount' => 50000,
            'form_fee_status' => 'paid',
        ]);
        $invoice = Invoice::factory()->create([
            'application_id' => $application->id,
            'total' => 900000,
            'status' => 'unpaid',
        ]);

        return [$application, $invoice];
    }

    private function guardianNotificationTitles(User $user): array
    {
        return $user->fresh()->notifications()->get()
            ->map(fn ($n) => $n->data['title'])->all();
    }

    public function test_form_fee_relief_revision_cycle_end_to_end(): void
    {
        $application = $this->feeApplication();

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');
        $this->assertSame(1, $this->admin()->fresh()->unreadNotifications()->count());

        // The committee asks for a revision, with a note.
        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), [
                'decision' => 'revision',
                'note' => 'Lampirkan surat keterangan tidak mampu dari RT.',
            ])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('revision', $application->form_fee_request_status);
        $this->assertSame(0, $this->admin()->fresh()->unreadNotifications()->count());
        $this->assertContains('Pengajuan Keringanan Formulir Perlu Direvisi', $this->guardianNotificationTitles($this->guardian));

        // The guardian sees the note, the prefilled reason and
        // the resubmit button on the payment page.
        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.payment', $application))
            ->assertOk()
            ->assertSee('Panitia meminta revisi pada pengajuan Anda.')
            ->assertSee('Lampirkan surat keterangan tidak mampu dari RT.')
            ->assertSee('Anak yatim')
            ->assertSee('Kirim Ulang Pengajuan');

        // Resubmitting returns the same request to pending and
        // notifies the officers again.
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim, SKTM terlampir'])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('pending', $application->form_fee_request_status);
        $this->assertSame('Anak yatim, SKTM terlampir', $application->form_fee_request_reason);
        $this->assertSame(1, $this->admin()->fresh()->unreadNotifications()->count());

        // Approval after resubmission applies the relief.
        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), [
                'decision' => 'approve', 'relief_type' => 'waiver',
            ])
            ->assertSessionHas('success');

        $this->assertSame('approved', $application->refresh()->form_fee_request_status);
        $this->assertSame('waived', $application->form_fee_status);
    }

    public function test_revision_decision_requires_a_note(): void
    {
        $application = $this->feeApplication();

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), ['decision' => 'revision'])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending', $application->refresh()->form_fee_request_status);
    }

    public function test_resubmission_without_a_new_file_keeps_the_existing_attachment(): void
    {
        Storage::fake('local');
        $application = $this->feeApplication();

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), [
                'reason' => 'Anak yatim',
                'attachment' => UploadedFile::fake()->image('sktm.jpg'),
            ])
            ->assertSessionHas('success');

        $path = $application->refresh()->form_fee_request_file_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), [
                'decision' => 'revision', 'note' => 'Perjelas alasannya saja.',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim piatu'])
            ->assertSessionHas('success');

        $this->assertSame($path, $application->refresh()->form_fee_request_file_path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_invoice_relief_revision_locks_other_requests_and_direct_grants(): void
    {
        [$application, $invoice] = $this->passedWithInvoice();
        $invoices = app(InvoiceServiceInterface::class);
        $installments = app(InstallmentServiceInterface::class);

        $invoices->requestDiscount($invoice, 'Penghasilan tidak tetap');
        $invoices->decideDiscount($invoice->fresh(), 'revision', null, null, $this->admin(), 'Sertakan slip gaji terakhir.');
        $this->assertSame('revision', $invoice->refresh()->request_status);
        $this->assertContains('Pengajuan Keringanan Tagihan Perlu Direvisi', $this->guardianNotificationTitles($this->guardian));

        // While the relief request awaits the guardian's fix,
        // an installment request and a direct grant are locked.
        $this->expectExceptionMessage('Masih ada pengajuan keringanan yang belum selesai.');
        $installments->requestInstallment($invoice->fresh(), 'Bayar bertahap');
    }

    public function test_direct_grant_is_blocked_while_relief_awaits_revision(): void
    {
        [, $invoice] = $this->passedWithInvoice();
        $invoices = app(InvoiceServiceInterface::class);

        $invoices->requestDiscount($invoice, 'Penghasilan tidak tetap');
        $invoices->decideDiscount($invoice->fresh(), 'revision', null, null, $this->admin(), 'Perjelas alasan.');

        $this->expectExceptionMessage('Ada pengajuan yang belum selesai');
        $invoices->grantDiscount($invoice->fresh(), 'waiver', null, $this->admin(), null);
    }

    public function test_installment_revision_cycle_and_approval_after_resubmission(): void
    {
        [, $invoice] = $this->passedWithInvoice();
        $installments = app(InstallmentServiceInterface::class);

        $installments->requestInstallment($invoice, 'Gaji bulanan');
        $installments->decideInstallmentRequest($invoice->fresh(), 'revision', null, $this->admin(), 'Sebutkan penghasilan per bulan.');

        $this->assertSame('revision', $invoice->refresh()->installment_request_status);
        $this->assertContains('Pengajuan Cicilan Perlu Direvisi', $this->guardianNotificationTitles($this->guardian));

        $installments->requestInstallment($invoice->fresh(), 'Gaji bulanan Rp 3 juta');
        $this->assertSame('pending', $invoice->refresh()->installment_request_status);

        $installments->decideInstallmentRequest($invoice->fresh(), 'approve', [
            ['amount' => 900000, 'due_date' => now()->addMonth()->toDateString()],
        ], $this->admin(), null);

        $this->assertSame('approved', $invoice->refresh()->installment_request_status);
        $this->assertSame(1, $invoice->installments()->count());
    }

    public function test_rejected_request_can_be_filed_again_as_a_new_request(): void
    {
        [, $invoice] = $this->passedWithInvoice();
        $invoices = app(InvoiceServiceInterface::class);

        $invoices->requestDiscount($invoice, 'Alasan pertama');
        $invoices->decideDiscount($invoice->fresh(), 'reject', null, null, $this->admin(), 'Belum memenuhi syarat.');
        $this->assertSame('rejected', $invoice->refresh()->request_status);

        $invoices->requestDiscount($invoice->fresh(), 'Alasan kedua dengan bukti baru');
        $invoice->refresh();
        $this->assertSame('pending', $invoice->request_status);
        $this->assertSame('Alasan kedua dengan bukti baru', $invoice->request_reason);
    }

    public function test_dashboard_flags_revision_requests_as_guardian_actions(): void
    {
        $application = $this->feeApplication();

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), [
                'decision' => 'revision', 'note' => 'Lampirkan SKTM.',
            ])
            ->assertSessionHas('success');

        $response = $this->actingAs($this->guardian)->get(route('guardian.dashboard'))->assertOk();
        $response->assertSee('Perbaiki Pengajuan')->assertSee('Lampirkan SKTM.');
        $labels = collect($response->viewData('actions'))->pluck('label')->all();
        $this->assertSame(['Perbaiki Pengajuan'], $labels);
    }
}
