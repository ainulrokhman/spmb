<?php

namespace Tests\Feature;

use App\Contracts\ReportServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class DashboardReportTest extends TestCase
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
     * One unit in the active year with applications in several
     * statuses, one settled form fee and one settled invoice.
     *
     * @return array{unit: SchoolUnit, year: AcademicYear, registered: Application, invoice: Invoice}
     */
    private function scenario(string $code = '31', string $childPrefix = 'Anak'): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => 'Unit '.$code, 'code' => $code]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id, 'name' => 'Reguler '.$code]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Gelombang 1',
        ]);
        $wave->admissionTracks()->attach($track);

        $make = fn (string $status, int $n) => Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $year->id,
            'child_name' => $childPrefix.' '.$status.' '.$n,
            'status' => $status,
            'registration_number' => $code.'260'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'submitted_at' => now(),
            'form_fee_amount' => 50000,
            'form_fee_status' => 'paid',
        ]);

        $make('submitted', 1);
        $make('submitted', 2);
        $make('verified', 3);
        $registered = $make('re_registered', 4);

        $registered->payments()->create([
            'channel' => 'manual', 'amount' => 50000, 'status' => 'paid',
            'reference' => 'FEE-SMOKE-'.$code, 'paid_at' => now(),
        ]);

        $invoice = Invoice::factory()->create([
            'application_id' => $registered->id,
            'total' => 1000000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $invoice->payments()->create([
            'channel' => 'manual', 'amount' => 1000000, 'status' => 'paid',
            'reference' => 'INV-SMOKE-'.$code, 'paid_at' => now(),
        ]);

        return ['unit' => $unit, 'year' => $year, 'registered' => $registered, 'invoice' => $invoice];
    }

    private function reports(): ReportServiceInterface
    {
        return app(ReportServiceInterface::class);
    }

    private function committee(SchoolUnit $unit): User
    {
        $role = Role::firstOrCreate(['name' => 'PanitiaLaporan']);
        $role->syncPermissions(['view-dashboard', 'application-list', 'invoice-list', 'invoice-manage']);

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->schoolUnits()->attach($unit);

        return $user;
    }

    public function test_dashboard_summary_counts_statuses_and_reception(): void
    {
        $this->scenario();

        $summary = $this->reports()->dashboardSummary(null);

        $this->assertSame(4, $summary['total_applicants']);
        $this->assertSame(2, $summary['status_counts']['submitted']);
        $this->assertSame(1, $summary['status_counts']['verified']);
        $this->assertSame(1, $summary['status_counts']['re_registered']);
        $this->assertSame('2026/2027', $summary['academic_year']->name);

        $row = collect($summary['units'])->firstWhere('unit.code', '31');
        $this->assertSame(4, $row['applicants']);
        $this->assertSame(1, $row['re_registered']);
        $this->assertSame(50000, $row['form_fees_received']);
        $this->assertSame(1000000, $row['invoices_received']);
        $this->assertSame(1050000, $row['total_received']);
    }

    public function test_dashboard_page_renders_spmb_summary(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Ringkasan Penerimaan Murid Baru');
        $response->assertSee('Unit 31');
        $response->assertSee('Pendaftar per Status');
    }

    public function test_dashboard_summary_respects_unit_scope(): void
    {
        ['unit' => $mine] = $this->scenario('31');
        $this->scenario('42', 'Lain');

        $summary = $this->reports()->dashboardSummary([$mine->id]);

        $this->assertSame(4, $summary['total_applicants']);
        $this->assertCount(1, $summary['units']);
    }

    public function test_unit_recap_breaks_down_and_aggregates_invoices(): void
    {
        ['unit' => $unit, 'year' => $year, 'registered' => $registered] = $this->scenario();

        // A second, discounted and unpaid invoice for the verified one.
        $other = Application::query()->where('status', 'verified')->firstOrFail();
        Invoice::factory()->create([
            'application_id' => $other->id,
            'total' => 500000,
            'status' => 'unpaid',
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]);

        $recap = $this->reports()->unitRecap($year->id, null);
        $row = collect($recap)->firstWhere('unit.id', $unit->id);

        $this->assertSame(4, $row['total_applicants']);
        $this->assertSame(4, $row['by_wave']['Gelombang 1']);
        $this->assertSame(4, $row['by_track']['Reguler 31']);
        $this->assertSame(2, $row['by_status']['submitted']);
        $this->assertSame(1, $row['re_registered']);
        $this->assertSame(2, $row['invoices_issued']);
        $this->assertSame(1, $row['invoices_paid']);
        $this->assertSame(1, $row['invoices_unpaid']);
        $this->assertSame(1500000, $row['billed_total']);
        $this->assertSame(50000, $row['discount_total']);
        $this->assertSame(450000, $row['outstanding_total']);
    }

    public function test_report_page_renders_recap(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->admin())->get(route('admin.reports.index'));
        $response->assertOk();
        $response->assertSee('Laporan SPMB');
        $response->assertSee('Unit 31');
        $response->assertSee('Daftar Ulang & Tagihan');
    }

    public function test_applications_csv_export_contains_rows(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->admin())->get(route('admin.reports.export.applications'));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Nomor Pendaftaran', $csv);
        $this->assertStringContainsString('Anak submitted 1', $csv);
        $this->assertStringContainsString('3126004', $csv);
    }

    public function test_invoices_csv_export_contains_rows(): void
    {
        $this->scenario();

        $response = $this->actingAs($this->admin())->get(route('admin.reports.export.invoices'));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Total Tagihan', $csv);
        $this->assertStringContainsString('1000000', $csv);
        $this->assertStringContainsString('Lunas', $csv);
    }

    public function test_csv_export_is_scoped_for_committee(): void
    {
        ['unit' => $mine] = $this->scenario('31');
        $this->scenario('42', 'Lain');

        $csv = $this->actingAs($this->committee($mine))
            ->get(route('admin.reports.export.applications'))
            ->streamedContent();

        $this->assertStringContainsString('Anak submitted 1', $csv);
        $this->assertStringNotContainsString('Lain submitted', $csv);
    }

    public function test_print_form_for_guardian_and_admin(): void
    {
        ['registered' => $application] = $this->scenario();

        $guardianView = $this->actingAs($this->guardian)
            ->get(route('guardian.applications.print', $application));
        $guardianView->assertOk();
        $guardianView->assertSee('FORMULIR PENDAFTARAN SPMB');

        $otherGuardian = User::factory()->create(['phone' => '089999999999']);
        $otherGuardian->assignRole('Wali');
        $this->actingAs($otherGuardian)
            ->get(route('guardian.applications.print', $application))
            ->assertNotFound();

        $adminView = $this->actingAs($this->admin())
            ->get(route('admin.applications.print', $application));
        $adminView->assertOk();
        $adminView->assertSee('FORMULIR PENDAFTARAN SPMB');
    }

    public function test_paid_invoice_page_offers_printable_proof(): void
    {
        ['registered' => $application] = $this->scenario();

        $response = $this->actingAs($this->guardian)
            ->get(route('guardian.invoices.show', $application));
        $response->assertOk();
        $response->assertSee('BUKTI DAFTAR ULANG SPMB');
        $response->assertSee('Cetak Bukti Daftar Ulang');
    }
}
