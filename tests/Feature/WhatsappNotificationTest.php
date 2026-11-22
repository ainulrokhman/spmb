<?php

namespace Tests\Feature;

use App\Contracts\ApplicationServiceInterface;
use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappMessageTemplate;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * WhatsApp Tahap 3: the automatic notifications that ride
 * on the same domain events as the in-app guardian
 * notifications, rendered from the Tahap 2 templates.
 */
class WhatsappNotificationTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private User $officer;

    private SchoolUnit $unit;

    private AdmissionWave $wave;

    private AdmissionTrack $track;

    private Application $application;

    /** @var array<int, array{device: ?string, message: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->officer = $this->superAdmin();

        config([
            'whatsapp.gowa.base_url' => 'http://gowa.test',
            'whatsapp.gowa.username' => 'admin',
            'whatsapp.gowa.password' => 'rahasia',
            'whatsapp.fonnte.base_url' => 'https://api.fonnte.com',
        ]);

        $this->guardian = User::factory()->create(['name' => 'Wali Notifikasi', 'phone' => '081200006666']);
        $this->guardian->assignRole('Wali');

        $this->unit = SchoolUnit::factory()->create(['name' => 'SMP Notifikasi', 'code' => '31', 'form_fee' => 100000]);
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $this->wave = AdmissionWave::factory()->create([
            'school_unit_id' => $this->unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Gelombang 1',
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->addDays(20),
            'announcement_at' => now()->addDays(10),
        ]);
        $this->track = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id, 'name' => 'Reguler']);
        $this->wave->admissionTracks()->attach($this->track);

        $this->application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $this->unit->id,
            'admission_wave_id' => $this->wave->id,
            'admission_track_id' => $this->track->id,
            'child_name' => 'Anak Notifikasi',
            'status' => 'awaiting_form_fee',
            'form_fee_amount' => 100000,
            'form_fee_status' => 'unpaid',
            'form_fee_request_status' => 'pending',
            'form_fee_request_reason' => 'Mohon keringanan',
        ]);

        WhatsappDevice::create([
            'school_unit_id' => $this->unit->id,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'unit-1',
        ]);
        WhatsappDevice::create([
            'school_unit_id' => null,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'yayasan-1',
        ]);

        $this->sent = [];
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/app/status')) {
                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => [
                        'is_connected' => true,
                        'is_logged_in' => true,
                        'jid' => '628987654321@s.whatsapp.net',
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/send/message')) {
                $this->sent[] = [
                    'device' => $request->header('X-Device-Id')[0] ?? null,
                    'message' => $request['message'] ?? '',
                ];

                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => ['message_id' => 'msg-1'],
                ]);
            }

            return Http::response([], 404);
        });
    }

    /** @return array<int, string> */
    private function sentMessagesOfType(string $type): array
    {
        return WhatsappMessageLog::query()
            ->where('type', $type)
            ->where('status', WhatsappMessageLog::STATUS_SENT)
            ->pluck('message')
            ->all();
    }

    public function test_registration_start_sends_the_registration_template_via_the_unit_device(): void
    {
        app(ApplicationServiceInterface::class)->start($this->guardian, $this->wave, $this->track, null, 'Anak Baru', 'ayah');

        $this->assertCount(1, $this->sent);
        $this->assertSame('unit-1', $this->sent[0]['device']);
        $this->assertStringContainsString('Anak Baru', $this->sent[0]['message']);
        $this->assertStringContainsString('SMP Notifikasi', $this->sent[0]['message']);
        $this->assertCount(1, $this->sentMessagesOfType(WhatsappMessageLog::TYPE_REGISTRATION));
    }

    public function test_form_fee_payment_sends_the_payment_template(): void
    {
        $payment = $this->application->payments()->create([
            'channel' => 'manual',
            'amount' => 100000,
            'status' => 'awaiting_confirmation',
            'reference' => 'FRM-WA-1',
        ]);

        app(PaymentService::class)->confirmPayment($payment);

        $messages = $this->sentMessagesOfType(WhatsappMessageLog::TYPE_PAYMENT);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Formulir', $messages[0]);
        $this->assertStringContainsString('Rp 100.000', $messages[0]);
        $this->assertSame('paid', $this->application->fresh()->form_fee_status);
    }

    public function test_invoice_paid_and_partial_installment_paid_send_payment_templates(): void
    {
        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 1000000,
        ]);
        $first = $invoice->installments()->create(['sequence' => 1, 'amount' => 700000, 'status' => 'unpaid', 'due_date' => now()->addDays(7)]);
        $invoice->installments()->create(['sequence' => 2, 'amount' => 300000, 'status' => 'unpaid', 'due_date' => now()->addDays(14)]);

        app(InstallmentServiceInterface::class)->settleInstallment($first);

        $messages = $this->sentMessagesOfType(WhatsappMessageLog::TYPE_PAYMENT);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Termin 1', $messages[0]);
        $this->assertStringContainsString('Rp 700.000', $messages[0]);

        // Settling the last term pays the invoice: the
        // invoice-paid message covers completion.
        $second = $invoice->installments()->where('sequence', 2)->firstOrFail();
        app(InstallmentServiceInterface::class)->settleInstallment($second);

        $messages = $this->sentMessagesOfType(WhatsappMessageLog::TYPE_PAYMENT);
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Daftar Ulang', $messages[1]);
    }

    public function test_relief_and_installment_decisions_send_the_decision_template(): void
    {
        app(ApplicationServiceInterface::class)->decideFeeRequest(
            $this->application, 'approve', 'percent', 50, $this->officer, null,
        );

        $messages = $this->sentMessagesOfType(WhatsappMessageLog::TYPE_DECISION);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('keringanan biaya formulir', $messages[0]);
        $this->assertStringContainsString('Disetujui', $messages[0]);
        $this->assertStringContainsString('Rp 50.000', $messages[0]);

        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 1000000,
            'installment_request_status' => 'pending',
        ]);

        app(InstallmentServiceInterface::class)->decideInstallmentRequest($invoice, 'approve', [
            ['amount' => 1000000, 'due_date' => now()->addDays(3)->toDateString()],
        ], $this->officer, null);

        $messages = $this->sentMessagesOfType(WhatsappMessageLog::TYPE_DECISION);
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('cicilan tagihan daftar ulang', $messages[1]);
    }

    public function test_due_reminders_send_once_per_day_and_again_on_the_due_date(): void
    {
        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 1000000,
            'installment_request_status' => 'pending',
        ]);
        app(InstallmentServiceInterface::class)->decideInstallmentRequest($invoice, 'approve', [
            ['amount' => 1000000, 'due_date' => now()->addDays(3)->toDateString()],
        ], $this->officer, null);

        $this->artisan('notifications:remind-installments')->assertSuccessful();
        $this->assertCount(1, $this->sentMessagesOfType(WhatsappMessageLog::TYPE_REMINDER));

        // Re-running the same day must not re-send.
        $this->artisan('notifications:remind-installments')->assertSuccessful();
        $this->assertCount(1, $this->sentMessagesOfType(WhatsappMessageLog::TYPE_REMINDER));

        // On the due date itself the reminder goes out again.
        $invoice->installments()->first()->update(['due_date' => now()->toDateString()]);
        $this->artisan('notifications:remind-installments')->assertSuccessful();
        $this->assertCount(2, $this->sentMessagesOfType(WhatsappMessageLog::TYPE_REMINDER));
    }

    public function test_result_whatsapp_waits_for_the_announcement_and_sends_exactly_once(): void
    {
        $this->application->update(['status' => 'verified']);
        app(ApplicationServiceInterface::class)->decideAnnouncement($this->application->fresh(), 'passed');

        // Announcement is still 10 days away: no WhatsApp.
        $this->artisan('whatsapp:notify-results')->assertSuccessful();
        $this->assertCount(0, $this->sentMessagesOfType(WhatsappMessageLog::TYPE_RESULT));
        $this->assertCount(0, $this->sent);

        // Once the announcement time arrives, the result goes
        // out — and only once, however often the sweep runs.
        $this->wave->update(['announcement_at' => now()->subHour()]);
        $this->artisan('whatsapp:notify-results')->assertSuccessful();

        $messages = $this->sentMessagesOfType(WhatsappMessageLog::TYPE_RESULT);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Lulus', $messages[0]);
        $this->assertStringContainsString('Anak Notifikasi', $messages[0]);

        $this->artisan('whatsapp:notify-results')->assertSuccessful();
        $this->assertCount(1, $this->sentMessagesOfType(WhatsappMessageLog::TYPE_RESULT));
    }

    public function test_a_dead_whatsapp_never_breaks_the_payment_flow(): void
    {
        WhatsappDevice::query()->delete();

        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 500000,
        ]);

        app(InvoiceServiceInterface::class)->markPaid($invoice);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            WhatsappMessageLog::STATUS_SKIPPED,
            WhatsappMessageLog::where('type', WhatsappMessageLog::TYPE_PAYMENT)->firstOrFail()->status,
        );
    }

    public function test_an_inactive_template_suppresses_the_automatic_send(): void
    {
        WhatsappMessageTemplate::create([
            'type' => WhatsappMessageLog::TYPE_PAYMENT,
            'content' => 'teks apa pun',
            'is_active' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 500000,
        ]);
        app(InvoiceServiceInterface::class)->markPaid($invoice);

        $this->assertCount(0, $this->sent);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(
            WhatsappMessageLog::STATUS_SKIPPED,
            WhatsappMessageLog::where('type', WhatsappMessageLog::TYPE_PAYMENT)->firstOrFail()->status,
        );
    }
}
