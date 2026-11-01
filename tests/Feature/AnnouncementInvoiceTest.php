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
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class AnnouncementInvoiceTest extends TestCase
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

    /**
     * A passed application on a fresh unit whose fees are: SPI
     * fixed (track amount beats global), SOP pilihan with three
     * tiers, INFAQ bebas with a Rp50.000 minimum.
     *
     * @return array{application: Application, unit: SchoolUnit, wave: AdmissionWave, fees: array<string, Fee>}
     */
    private function passedApplication(?string $announcementAt = 'past', string $unitCode = '31', string $childName = 'Anak Lulus'): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Uji '.$unitCode, 'code' => $unitCode]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'announcement_at' => match ($announcementAt) {
                'past' => now()->subHour(),
                'future' => now()->addDay(),
                default => null,
            },
        ]);
        $wave->admissionTracks()->attach($track);

        $spi = FeeComponent::factory()->create(['code' => 'SPI'.$unitCode, 'name' => 'Sumbangan Pengembangan Institusi']);
        $sop = FeeComponent::factory()->create(['code' => 'SOP'.$unitCode, 'name' => 'Sumbangan Operasional']);
        $infaq = FeeComponent::factory()->create(['code' => 'INFAQ'.$unitCode, 'name' => 'Infaq']);

        $spiFee = Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $spi->id, 'mode' => 'fixed']);
        FeeAmount::factory()->create(['fee_id' => $spiFee->id, 'amount' => 1000000]);
        FeeAmount::factory()->create(['fee_id' => $spiFee->id, 'admission_track_id' => $track->id, 'amount' => 1500000]);

        $sopFee = Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $sop->id, 'mode' => 'pilihan']);
        foreach ([500000, 750000, 1000000] as $i => $amount) {
            FeeAmount::factory()->create(['fee_id' => $sopFee->id, 'amount' => $amount, 'sort_order' => $i]);
        }

        $infaqFee = Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $infaq->id, 'mode' => 'bebas', 'min_amount' => 50000]);

        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $year->id,
            'child_name' => $childName,
            'gender' => 'L',
            'status' => 'passed',
            'registration_number' => $unitCode.'26001',
            'submitted_at' => now(),
        ]);

        return [
            'application' => $application,
            'unit' => $unit,
            'wave' => $wave,
            'fees' => ['spi' => $spiFee, 'sop' => $sopFee, 'infaq' => $infaqFee],
        ];
    }

    private function invoices(): InvoiceServiceInterface
    {
        return app(InvoiceServiceInterface::class);
    }

    public function test_bulk_announcement_sets_verified_applications_only(): void
    {
        ['wave' => $wave] = $this->passedApplication();
        $verified = Application::factory()->create([
            'admission_wave_id' => $wave->id, 'school_unit_id' => $wave->schoolUnit->id,
            'status' => 'verified', 'registration_number' => '3126002',
        ]);
        $submitted = Application::factory()->create([
            'admission_wave_id' => $wave->id, 'school_unit_id' => $wave->schoolUnit->id,
            'status' => 'submitted', 'registration_number' => '3126003',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.applications.announce'), [
                'decision' => 'passed',
                'admission_wave_id' => $wave->id,
            ])
            ->assertRedirect(route('admin.applications.index'));

        $this->assertSame('passed', $verified->fresh()->status);
        $this->assertSame('submitted', $submitted->fresh()->status);
    }

    public function test_guardian_sees_result_only_after_announcement_time(): void
    {
        ['application' => $application, 'wave' => $wave] = $this->passedApplication('future');

        $hidden = $this->actingAs($this->guardian)->get(route('guardian.dashboard'));
        $hidden->assertOk();
        $hidden->assertSee('Menunggu Pengumuman');
        $hidden->assertDontSee('Lulus!');

        $wave->update(['announcement_at' => now()->subMinute()]);

        $shown = $this->actingAs($this->guardian)->get(route('guardian.dashboard'));
        $shown->assertSee('Lulus!');
        $shown->assertSee('Daftar Ulang');
    }

    public function test_invoice_freezes_resolved_snapshot_and_is_unique(): void
    {
        ['application' => $application, 'fees' => $fees] = $this->passedApplication();

        $invoice = $this->invoices()->createInvoice($application, [
            $fees['sop']->id => ['choice' => 750000],
            $fees['infaq']->id => ['free' => 100000],
        ]);

        // SPI track amount (1.500.000) + SOP tier (750.000) + INFAQ (100.000)
        $this->assertSame(2350000, $invoice->total);
        $this->assertSame(3, $invoice->items()->count());
        $this->assertSame(1500000, (int) $invoice->items()->where('component_code', 'SPI31')->value('amount'));

        $again = $this->invoices()->createInvoice($application, []);
        $this->assertSame($invoice->id, $again->id);
        $this->assertSame(1, Invoice::count());
    }

    public function test_invoice_rejects_invalid_tier_and_below_minimum_free_amount(): void
    {
        ['application' => $application, 'fees' => $fees] = $this->passedApplication();

        try {
            $this->invoices()->createInvoice($application, [
                $fees['sop']->id => ['choice' => 123456],
                $fees['infaq']->id => ['free' => 100000],
            ]);
            $this->fail('Invalid tier was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, Invoice::count());
        }

        try {
            $this->invoices()->createInvoice($application, [
                $fees['sop']->id => ['choice' => 500000],
                $fees['infaq']->id => ['free' => 10000],
            ]);
            $this->fail('Below-minimum free amount was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, Invoice::count());
        }
    }

    public function test_invoice_creation_requires_passed_status_and_announcement(): void
    {
        ['application' => $application] = $this->passedApplication(null);

        $this->expectException(InvalidArgumentException::class);
        $this->invoices()->createInvoice($application, []);
    }

    public function test_full_manual_payment_flow_registers_the_child(): void
    {
        ['application' => $application, 'fees' => $fees] = $this->passedApplication();

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.store', $application), [
                'choice' => [$fees['sop']->id => 500000],
                'free' => [$fees['infaq']->id => 50000],
            ])
            ->assertRedirect(route('guardian.invoices.show', $application));

        $invoice = $application->invoice()->firstOrFail();
        $this->assertSame(2050000, $invoice->total);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.pay', $application), ['channel' => 'manual'])
            ->assertRedirect(route('guardian.invoices.show', $application));

        $payment = $invoice->payments()->firstOrFail();
        $this->assertStringStartsWith('INV-', $payment->reference);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.declare-paid', [$application, $payment]))
            ->assertRedirect();

        $this->assertSame('awaiting_confirmation', $invoice->fresh()->status);

        $this->actingAs($this->admin())
            ->post(route('admin.invoices.confirm', $payment))
            ->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('re_registered', $application->fresh()->status);
    }

    public function test_invoice_discount_approved_reduces_payable(): void
    {
        ['application' => $application, 'fees' => $fees] = $this->passedApplication();

        $invoice = $this->invoices()->createInvoice($application, [
            $fees['sop']->id => ['choice' => 1000000],
            $fees['infaq']->id => ['free' => 50000],
        ]);
        $this->assertSame(2550000, $invoice->total);

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-discount', $application), [
                'kind' => 'discount', 'type' => 'percent', 'value' => 50, 'reason' => 'Anak karyawan yayasan',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->post(route('admin.invoices.decide-discount', $invoice), ['decision' => 'approve'])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(1275000, $invoice->discountAmount());
        $this->assertSame(1275000, $invoice->payableAmount());
        $this->assertSame('unpaid', $invoice->status);
    }

    public function test_invoice_waiver_approval_settles_and_registers(): void
    {
        ['application' => $application, 'fees' => $fees] = $this->passedApplication();

        $invoice = $this->invoices()->createInvoice($application, [
            $fees['sop']->id => ['choice' => 500000],
            $fees['infaq']->id => ['free' => 50000],
        ]);

        $this->invoices()->requestDiscount($invoice, 'waiver', null, null, 'Anak yatim');
        $this->invoices()->decideDiscount($invoice->fresh(), true, $this->admin(), null);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('re_registered', $application->fresh()->status);
    }

    public function test_admin_invoice_list_is_scoped_to_assigned_units(): void
    {
        ['application' => $mine, 'fees' => $fees] = $this->passedApplication();
        $this->invoices()->createInvoice($mine, [
            $fees['sop']->id => ['choice' => 500000],
            $fees['infaq']->id => ['free' => 50000],
        ]);

        $this->passedApplication('past', '42', 'Anak Unit Lain');

        $role = Role::firstOrCreate(['name' => 'PanitiaTagihan']);
        $role->syncPermissions(['invoice-list', 'invoice-manage']);
        $committee = User::factory()->create();
        $committee->assignRole($role);
        $committee->schoolUnits()->attach($mine->school_unit_id);

        $response = $this->actingAs($committee)->get(route('admin.invoices.index'));
        $response->assertOk();
        $response->assertSee('Anak Lulus');
        $response->assertDontSee('Anak Unit Lain');
    }

    public function test_announcement_requires_verified_status_in_service(): void
    {
        ['application' => $application] = $this->passedApplication();
        $application->update(['status' => 'submitted']);

        $this->expectException(InvalidArgumentException::class);
        app(\App\Contracts\ApplicationServiceInterface::class)->decideAnnouncement($application->fresh(), 'passed');
    }
}
