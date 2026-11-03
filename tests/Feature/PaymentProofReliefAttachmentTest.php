<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 5: manual payment declarations require a transfer
 * proof, relief requests may carry a supporting document, and
 * both are served to the committee through unit-checked
 * private routes.
 */
class PaymentProofReliefAttachmentTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $admin;

    private User $guardian;

    private Application $application;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        Storage::fake('local');

        $this->admin = User::where('email', 'superadmin@example.com')->firstOrFail();

        $this->guardian = User::factory()->create();
        $this->guardian->assignRole('Wali');

        $this->application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'status' => 'awaiting_form_fee',
            'form_fee_status' => 'unpaid',
            'form_fee_amount' => 50000,
        ]);

        $this->payment = Payment::factory()->create([
            'payable_type' => Application::class,
            'payable_id' => $this->application->id,
            'channel' => 'manual',
            'status' => 'pending',
            'amount' => 50000,
        ]);
    }

    public function test_manual_declaration_requires_a_proof_file(): void
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.declare-paid', [$this->application, $this->payment]))
            ->assertSessionHasErrors('proof');

        $this->assertSame('pending', $this->payment->fresh()->status);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.declare-paid', [$this->application, $this->payment]), [
                'proof' => UploadedFile::fake()->create('bukti.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('proof');
    }

    public function test_manual_declaration_stores_the_proof_and_redeclaration_replaces_it(): void
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.declare-paid', [$this->application, $this->payment]), [
                'proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertSessionDoesntHaveErrors();

        $payment = $this->payment->fresh();
        $this->assertSame('awaiting_confirmation', $payment->status);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        $firstPath = $payment->proof_path;

        // The committee sends it back; the guardian declares again
        // with a new proof and the old file is removed.
        $payment->update(['status' => 'pending']);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.declare-paid', [$this->application, $this->payment]), [
                'proof' => UploadedFile::fake()->create('bukti-baru.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionDoesntHaveErrors();

        $payment = $this->payment->fresh();
        $this->assertNotSame($firstPath, $payment->proof_path);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    public function test_proof_is_served_only_to_staff_with_unit_access(): void
    {
        $this->payment->update(['proof_path' => 'bukti-bayar/contoh.jpg']);
        Storage::disk('local')->put('bukti-bayar/contoh.jpg', 'isi-bukti');

        $this->get(route('admin.payments.proof', $this->payment))
            ->assertRedirect(route('login'));

        $this->actingAs($this->admin)
            ->get(route('admin.payments.proof', $this->payment))
            ->assertOk();

        $otherUnit = SchoolUnit::factory()->create();
        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');
        $panitia->schoolUnits()->attach($otherUnit->id);

        $this->actingAs($panitia)
            ->get(route('admin.payments.proof', $this->payment))
            ->assertForbidden();
    }

    public function test_form_fee_relief_attachment_is_optional_and_replaced_on_a_new_request(): void
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $this->application), [
                'reason' => 'Keluarga tidak mampu.',
                'attachment' => UploadedFile::fake()->create('sktm.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionDoesntHaveErrors();

        $application = $this->application->fresh();
        $this->assertNotNull($application->form_fee_request_file_path);
        Storage::disk('local')->assertExists($application->form_fee_request_file_path);
        $firstPath = $application->form_fee_request_file_path;

        // Committee can open the attachment; a decided request can
        // be followed by a new one without a file, which clears it.
        $this->actingAs($this->admin)
            ->get(route('admin.applications.relief-file', $this->application))
            ->assertOk();

        $application->update(['form_fee_request_status' => 'rejected']);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $this->application), [
                'reason' => 'Mengajukan kembali.',
            ])
            ->assertSessionDoesntHaveErrors();

        $application = $this->application->fresh();
        $this->assertNull($application->form_fee_request_file_path);
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_invoice_relief_attachment_and_invoice_payment_proof(): void
    {
        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 1000000,
        ]);
        $this->application->update(['status' => 'passed']);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-discount', $this->application), [
                'reason' => 'Mohon keringanan.',
                'attachment' => UploadedFile::fake()->image('surat.jpg'),
            ])
            ->assertSessionDoesntHaveErrors();

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->request_file_path);
        Storage::disk('local')->assertExists($invoice->request_file_path);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.relief-file', $invoice))
            ->assertOk();

        $invoicePayment = Payment::factory()->create([
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
            'channel' => 'manual',
            'status' => 'pending',
            'amount' => 1000000,
        ]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.declare-paid', [$this->application, $invoicePayment]), [
                'proof' => UploadedFile::fake()->image('lunas.jpg'),
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertNotNull($invoicePayment->fresh()->proof_path);
    }

    public function test_installment_declaration_also_requires_a_proof(): void
    {
        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 600000,
        ]);
        $installment = InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 300000,
            'status' => 'unpaid',
        ]);
        $termPayment = Payment::factory()->create([
            'payable_type' => InvoiceInstallment::class,
            'payable_id' => $installment->id,
            'channel' => 'manual',
            'status' => 'pending',
            'amount' => 300000,
        ]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.declare-installment-paid', [$this->application, $termPayment]))
            ->assertSessionHasErrors('proof');

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.declare-installment-paid', [$this->application, $termPayment]), [
                'proof' => UploadedFile::fake()->image('termin.jpg'),
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertNotNull($termPayment->fresh()->proof_path);
    }
}
