<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 3 of the fee/UX filter work: the operational lists keep
 * their filters across the detail round trip — Verifikasi
 * Pendaftaran (unit/wave/track/status) and Tagihan (status).
 * Pembayaran Formulir has no filters and its actions already
 * return with back(); the Laporan page already round-trips its
 * GET filters into its exports — both are locked in here.
 */
class OperationalFilterPersistenceTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->admin = User::where('email', 'superadmin@example.com')->firstOrFail();
    }

    public function test_application_filters_are_selected_from_the_url_and_carried_by_the_announce_form(): void
    {
        $unit = SchoolUnit::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.applications.index', ['school_unit_id' => $unit->id, 'status' => 'submitted']))
            ->assertOk()
            ->assertSee('value="'.$unit->id.'" selected', false)
            ->assertSee('name="return_school_unit_id" id="return-unit" value="'.$unit->id.'"', false)
            ->assertSee('name="return_status" id="return-status" value="submitted"', false);
    }

    public function test_application_row_actions_carry_the_active_filters_to_the_detail_page(): void
    {
        $application = Application::factory()->create(['status' => 'submitted', 'registration_number' => '0326001']);
        $unit = $application->schoolUnit;

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.applications.index', [
                'school_unit_id' => $unit->id,
                'status' => 'submitted',
            ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $application->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString('school_unit_id='.$unit->id, $row['actions']);
        $this->assertStringContainsString('status=submitted', $row['actions']);
    }

    public function test_detail_page_back_link_and_decision_keep_the_filters(): void
    {
        $application = Application::factory()->create(['status' => 'submitted']);
        $unit = $application->schoolUnit;
        $query = ['school_unit_id' => $unit->id, 'status' => 'submitted'];

        $this->actingAs($this->admin)
            ->get(route('admin.applications.show', [$application] + $query))
            ->assertOk()
            // The breadcrumb href carries the filters (HTML-escaped &).
            ->assertSee('admin/applications?school_unit_id='.$unit->id.'&amp;status=submitted', false)
            ->assertSee('name="return_school_unit_id" value="'.$unit->id.'"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.applications.decide', $application), [
                'decision' => 'needs_revision',
                'note' => 'Perbaiki berkas.',
                'return_school_unit_id' => $unit->id,
                'return_status' => 'submitted',
            ])
            ->assertRedirect(route('admin.applications.show', [$application] + $query));
    }

    public function test_bulk_announce_returns_to_the_filtered_list_and_single_announce_stays_on_the_detail(): void
    {
        $application = Application::factory()->create(['status' => 'verified']);
        $unit = $application->schoolUnit;

        $this->actingAs($this->admin)
            ->post(route('admin.applications.announce'), [
                'decision' => 'passed',
                'return_school_unit_id' => $unit->id,
                'return_status' => 'verified',
            ])
            ->assertRedirect(route('admin.applications.index', ['school_unit_id' => $unit->id, 'status' => 'verified']));

        $showUrl = route('admin.applications.show', $application);

        $this->actingAs($this->admin)
            ->from($showUrl)
            ->post(route('admin.applications.announce'), [
                'decision' => 'passed',
                'application_ids' => [$application->id],
            ])
            ->assertRedirect($showUrl);
    }

    public function test_invoice_status_filter_survives_the_detail_round_trip(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'unpaid']);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.index', ['status' => 'unpaid']))
            ->assertOk()
            ->assertSee(route('admin.invoices.show', [$invoice, 'status' => 'unpaid']), false);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', [$invoice, 'status' => 'unpaid']))
            ->assertOk()
            ->assertSee(route('admin.invoices.index', ['status' => 'unpaid']), false);
    }

    public function test_report_filters_round_trip_into_the_export_links(): void
    {
        $unit = SchoolUnit::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['school_unit_id' => $unit->id]))
            ->assertOk()
            ->assertSee(route('admin.reports.export.applications', ['school_unit_id' => $unit->id]), false);
    }
}
