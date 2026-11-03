<?php

namespace Tests\Feature\Guardian;

use App\Contracts\ApplicationServiceInterface;
use App\Contracts\GuardianNotificationServiceInterface;
use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Services\PaymentService;
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
 * Guardian (wali) notifications: committee decisions,
 * verification outcomes, payment states, and installment
 * reminders reach the account holder and linked parents —
 * while selection results stay hidden until the
 * announcement time.
 */
class GuardianNotificationTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private User $linkedParent;

    private User $officer;

    private Application $application;

    private AdmissionWave $wave;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->officer = $this->superAdmin();

        $this->guardian = User::factory()->create(['phone' => '081200006666']);
        $this->guardian->assignRole('Wali');

        $this->linkedParent = User::factory()->create(['phone' => '081200005555']);
        $this->linkedParent->assignRole('Wali');

        $unit = SchoolUnit::factory()->create(['name' => 'SMP Notifikasi']);
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $this->wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->addDays(20),
            'announcement_at' => now()->addDays(10),
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);

        $this->application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $this->wave->id,
            'admission_track_id' => $track->id,
            'child_name' => 'Anak Notifikasi',
            'status' => 'awaiting_form_fee',
            'form_fee_amount' => 100000,
            'form_fee_status' => 'unpaid',
            'form_fee_request_status' => 'pending',
            'form_fee_request_reason' => 'Mohon keringanan',
        ]);

        $this->application->linkedUsers()->attach($this->linkedParent->id, ['relation' => 'ibu']);
    }

    private function guardianNotificationCount(User $user): int
    {
        return $user->notifications()->count();
    }

    public function test_form_relief_decision_notifies_holder_and_linked_parent(): void
    {
        app(ApplicationServiceInterface::class)->decideFeeRequest(
            $this->application, true, 'percent', 50, $this->officer, null,
        );

        foreach ([$this->guardian, $this->linkedParent] as $user) {
            $this->assertSame(1, $this->guardianNotificationCount($user->fresh()));
            $notification = $user->fresh()->notifications()->first();
            $this->assertSame('Keringanan Biaya Formulir Disetujui', $notification->data['title']);
            $this->assertStringContainsString('Anak Notifikasi', $notification->data['message']);
            $this->assertStringContainsString('Rp 50.000', $notification->data['message']);
        }
    }

    public function test_verification_decision_notifies_with_committee_note(): void
    {
        $this->application->update(['status' => 'submitted']);

        app(ApplicationServiceInterface::class)->decideVerification(
            $this->application->fresh(), 'needs_revision', 'Kartu Keluarga buram', $this->officer,
        );

        $notification = $this->guardian->fresh()->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Pendaftaran Perlu Perbaikan', $notification->data['title']);
        $this->assertStringContainsString('Kartu Keluarga buram', $notification->data['message']);
    }

    public function test_result_notification_stays_hidden_until_announcement(): void
    {
        $this->application->update(['status' => 'verified']);

        app(ApplicationServiceInterface::class)->decideAnnouncement($this->application->fresh(), 'passed');

        // The row exists, but neither the list nor the bell
        // may show it before the announcement time.
        $this->assertSame(1, $this->guardianNotificationCount($this->guardian->fresh()));
        $this->assertSame(0, app(GuardianNotificationServiceInterface::class)->visibleUnread($this->guardian->fresh())->count());

        $this->actingAs($this->guardian)
            ->get(route('guardian.notifications.index'))
            ->assertOk()
            ->assertDontSee('Hasil Pengumuman: Lulus');

        $notification = $this->guardian->fresh()->notifications()->first();
        $this->actingAs($this->guardian)
            ->get(route('guardian.notifications.show', $notification))
            ->assertNotFound();

        // Once the announcement time passes, it appears.
        $this->wave->update(['announcement_at' => now()->subHour()]);

        $this->assertSame(1, app(GuardianNotificationServiceInterface::class)->visibleUnread($this->guardian->fresh())->count());

        $this->actingAs($this->guardian)
            ->get(route('guardian.notifications.index'))
            ->assertOk()
            ->assertSee('Hasil Pengumuman: Lulus');
    }

    public function test_form_fee_confirmation_notifies_the_guardian(): void
    {
        $payment = $this->application->payments()->create([
            'channel' => 'manual',
            'amount' => 100000,
            'status' => 'awaiting_confirmation',
            'reference' => 'FRM-NOTIF-1',
        ]);

        app(PaymentService::class)->confirmPayment($payment);

        $notification = $this->guardian->fresh()->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Pembayaran Formulir Terkonfirmasi', $notification->data['title']);
        $this->assertSame('paid', $this->application->fresh()->form_fee_status);
    }

    public function test_installment_decision_and_due_reminders(): void
    {
        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 1000000,
            'installment_request_status' => 'pending',
        ]);

        $installments = app(InstallmentServiceInterface::class);
        $installments->decideInstallmentRequest($invoice, true, [
            ['amount' => 1000000, 'due_date' => now()->addDays(3)->toDateString()],
        ], $this->officer, null);

        $titles = $this->guardian->fresh()->notifications()->get()->map(fn ($n) => $n->data['title']);
        $this->assertTrue($titles->contains('Pengajuan Cicilan Disetujui'));

        // H-3 reminder: created once, deduplicated on re-run.
        $this->artisan('notifications:remind-installments')->assertSuccessful();
        $dueCount = fn () => $this->guardian->fresh()->notifications()
            ->where('data->kind', 'general')
            ->get()
            ->filter(fn ($n) => str_starts_with($n->data['event_key'] ?? '', 'installment_due:'))
            ->count();
        $this->assertSame(1, $dueCount());

        $this->artisan('notifications:remind-installments')->assertSuccessful();
        $this->assertSame(1, $dueCount());

        // On the due date itself, a second reminder follows.
        $invoice->installments()->first()->update(['due_date' => now()->toDateString()]);
        $this->artisan('notifications:remind-installments')->assertSuccessful();
        $this->assertSame(2, $dueCount());
    }

    public function test_invoice_paid_notifies_re_registration_completed(): void
    {
        $this->application->update(['status' => 'passed']);
        $this->wave->update(['announcement_at' => now()->subHour()]);

        $invoice = Invoice::factory()->create([
            'application_id' => $this->application->id,
            'status' => 'unpaid',
            'total' => 500000,
        ]);

        app(InvoiceServiceInterface::class)->markPaid($invoice);

        $notification = $this->guardian->fresh()->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Daftar Ulang Selesai', $notification->data['title']);
        $this->assertSame('re_registered', $this->application->fresh()->status);
    }
}
