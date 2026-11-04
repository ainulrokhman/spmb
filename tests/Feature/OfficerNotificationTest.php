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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 7: officer notifications (in-app). Guardians' relief /
 * installment requests and manual payment declarations notify
 * the officers who can decide them — global roles always,
 * Panitia only for their units and only with the deciding
 * permission. Deciding the matter marks every officer's copy
 * read.
 */
class OfficerNotificationTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->guardian = User::factory()->create(['phone' => '081200005555']);
        $this->guardian->assignRole('Wali');
    }

    private function committeeFor(SchoolUnit $unit): User
    {
        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');
        $panitia->schoolUnits()->sync([$unit->id]);

        return $panitia;
    }

    private function unread(User $user): int
    {
        return $user->fresh()->unreadNotifications()->count();
    }

    public function test_form_fee_relief_request_notifies_only_deciders_of_that_unit(): void
    {
        $unit = SchoolUnit::factory()->create();
        $otherUnit = SchoolUnit::factory()->create();
        $panitia = $this->committeeFor($unit);
        $otherPanitia = $this->committeeFor($otherUnit);
        $plainMember = User::factory()->create();
        $plainMember->assignRole('User');
        $plainMember->schoolUnits()->sync([$unit->id]);

        $application = Application::factory()->awaitingFee(50000)->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
        ]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');

        $this->assertSame(1, $this->unread($this->superAdmin()));
        $this->assertSame(1, $this->unread($this->admin()));
        $this->assertSame(1, $this->unread($panitia));
        $this->assertSame(0, $this->unread($otherPanitia));
        $this->assertSame(0, $this->unread($plainMember));

        $notification = $panitia->fresh()->notifications()->first();
        $this->assertSame('Pengajuan Keringanan Formulir', $notification->data['title']);
        $this->assertSame('form_relief:'.$application->id, $notification->data['event_key']);

        // The committee decides: every officer's copy is read.
        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), [
                'decision' => 'approve', 'relief_type' => 'percent', 'relief_value' => 50,
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, $this->unread($this->admin()));
        $this->assertSame(0, $this->unread($panitia));
        $this->assertSame(0, $this->unread($this->superAdmin()));
    }

    /**
     * Stored notification links must be relative paths: an
     * absolute URL freezes the APP_URL of creation time into
     * the database, so moving domains (localhost → production)
     * would strand every older notification on the old address.
     */
    public function test_stored_notification_url_is_a_relative_path(): void
    {
        $unit = SchoolUnit::factory()->create();
        $panitia = $this->committeeFor($unit);

        $application = Application::factory()->awaitingFee(50000)->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
        ]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');

        $url = $panitia->fresh()->notifications()->first()->data['url'];

        $this->assertSame(route('admin.form-payments.index', [], false), $url);
        $this->assertStringNotContainsString('://', $url);
    }

    public function test_manual_form_payment_declaration_notifies_and_confirmation_clears(): void
    {
        $unit = SchoolUnit::factory()->create();
        $panitia = $this->committeeFor($unit);

        $application = Application::factory()->awaitingFee(50000)->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
        ]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.pay', $application), ['channel' => 'manual'])
            ->assertSessionHas('success');

        $payment = $application->payments()->latest('id')->firstOrFail();

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.declare-paid', [$application, $payment]), ['proof' => UploadedFile::fake()->image('bukti.jpg')])
            ->assertSessionHas('success');

        $this->assertSame(1, $this->unread($panitia));
        $this->assertSame('Pembayaran Manual Menunggu Konfirmasi', $panitia->fresh()->notifications()->first()->data['title']);

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.confirm', $payment))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->unread($panitia));
        $this->assertSame(0, $this->unread($this->admin()));
    }

    /**
     * @return array{application: Application, invoice: Invoice, unit: SchoolUnit}
     */
    private function passedWithInvoice(string $unitCode): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Notif '.$unitCode, 'code' => $unitCode]);
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

        $invoice = app(InvoiceServiceInterface::class)->createInvoice($application, []);

        return ['application' => $application, 'invoice' => $invoice, 'unit' => $unit];
    }

    public function test_invoice_relief_and_installment_requests_notify_invoice_managers(): void
    {
        ['application' => $application, 'invoice' => $invoice, 'unit' => $unit] = $this->passedWithInvoice('81');
        $panitia = $this->committeeFor($unit);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-discount', $application), ['reason' => 'Minta keringanan'])
            ->assertSessionHas('success');

        $this->assertSame(1, $this->unread($panitia));
        $this->assertSame('Pengajuan Keringanan Tagihan', $panitia->fresh()->notifications()->first()->data['title']);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.decide-discount', $invoice), [
                'decision' => 'reject',
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, $this->unread($panitia));

        // Installment request: same recipients, cleared on decision.
        ['application' => $application2, 'invoice' => $invoice2, 'unit' => $unit2] = $this->passedWithInvoice('82');
        $panitia2 = $this->committeeFor($unit2);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-installment', $application2), ['reason' => 'Minta cicilan'])
            ->assertSessionHas('success');

        $this->assertSame(1, $this->unread($panitia2));
        $this->assertSame('Pengajuan Cicilan', $panitia2->fresh()->notifications()->first()->data['title']);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.decide-installment', $invoice2), ['decision' => 'reject'])
            ->assertSessionHas('success');

        $this->assertSame(0, $this->unread($panitia2));
    }

    public function test_bell_list_page_open_and_mark_all_read(): void
    {
        $unit = SchoolUnit::factory()->create();
        $application = Application::factory()->awaitingFee(50000)->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
        ]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), ['reason' => 'Anak yatim'])
            ->assertSessionHas('success');

        // The bell badge shows the unread count on admin pages.
        $this->actingAs($this->admin())
            ->get(route('admin.form-payments.index'))
            ->assertOk()
            ->assertSee('1 Notifikasi Belum Dibaca');

        // The list page shows the notification.
        $this->actingAs($this->admin())
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Pengajuan Keringanan Formulir')
            ->assertSee('Baru');

        // Opening it marks it read and continues to the worklist.
        $notification = $this->admin()->fresh()->notifications()->firstOrFail();
        $this->actingAs($this->admin())
            ->get(route('admin.notifications.show', $notification))
            ->assertRedirect(route('admin.form-payments.index'));
        $this->assertSame(0, $this->unread($this->admin()));

        // Another officer's copy is untouched by opening; the
        // mark-all endpoint clears the super admin's own list.
        $this->assertSame(1, $this->unread($this->superAdmin()));
        $this->actingAs($this->superAdmin())
            ->post(route('admin.notifications.read-all'))
            ->assertSessionHas('success');
        $this->assertSame(0, $this->unread($this->superAdmin()));

        // Someone else's notification cannot be opened.
        $foreign = $this->admin()->fresh()->notifications()->first();
        if ($foreign !== null) {
            $this->actingAs($this->superAdmin())
                ->get(route('admin.notifications.show', $foreign))
                ->assertNotFound();
        }
    }

    public function test_prune_command_deletes_only_old_notifications(): void
    {
        $admin = $this->admin();

        DB::table('notifications')->insert([
            [
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\FormFeeReliefRequestedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $admin->id,
                'data' => json_encode(['event_key' => 'form_relief:1', 'title' => 'Lama']),
                'read_at' => null,
                'created_at' => now()->subDays(100),
                'updated_at' => now()->subDays(100),
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\FormFeeReliefRequestedNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $admin->id,
                'data' => json_encode(['event_key' => 'form_relief:2', 'title' => 'Baru']),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Artisan::call('notifications:prune');

        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->assertSame('Baru', $admin->fresh()->notifications()->first()->data['title']);
    }
}
