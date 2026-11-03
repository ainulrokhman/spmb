<?php

namespace Tests\Feature;

use App\Contracts\FeeServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\ChildStatus;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use App\Models\User;
use Database\Seeders\SpmbMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class ChildStatusTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private int $codeSeq = 50;

    /**
     * A Panitia user assigned to the given units only.
     */
    private function panitiaFor(SchoolUnit ...$units): User
    {
        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');
        $panitia->schoolUnits()->sync(collect($units)->map->id->all());

        return $panitia;
    }

    private ChildStatus $reguler;

    private ChildStatus $yatim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->reguler = ChildStatus::create(['name' => 'Reguler', 'is_active' => true]);
        $this->yatim = ChildStatus::create(['name' => 'Yatim', 'is_active' => true]);

        $this->guardian = User::factory()->create(['phone' => '081200009999']);
        $this->guardian->assignRole('Wali');
    }

    /**
     * A free-form-fee unit with an open wave, so a started
     * application can immediately fill its biodata.
     *
     * @return array{unit: SchoolUnit, wave: AdmissionWave, track: AdmissionTrack}
     */
    private function scenario(): array
    {
        $year = AcademicYear::factory()->create(['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['code' => (string) (++$this->codeSeq), 'form_fee' => 0]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave->admissionTracks()->attach($track->id);

        return ['unit' => $unit, 'wave' => $wave, 'track' => $track];
    }

    private function startedApplication(array $scenario): Application
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), [
                'child_name' => 'Anak Uji',
                'guardian_relation' => 'ayah',
                'school_unit_id' => $scenario['unit']->id,
                'admission_wave_id' => $scenario['wave']->id,
                'admission_track_id' => $scenario['track']->id,
            ])
            ->assertRedirect();

        return Application::latest('id')->firstOrFail();
    }

    private function biodataPayload(array $extra = []): array
    {
        return array_merge([
            'child_name' => 'Anak Uji',
            'birth_place' => 'Jakarta',
            'birth_date' => '2016-05-01',
            'gender' => 'L',
            'father' => ['name' => 'Bapak', 'status' => 'Masih Hidup'],
            'mother' => ['name' => 'Ibu', 'status' => 'Masih Hidup'],
        ], $extra);
    }

    // ---------------------------------------------------------------
    // A1 — master data module
    // ---------------------------------------------------------------

    public function test_master_seeder_creates_the_six_statuses(): void
    {
        $this->seed(SpmbMasterSeeder::class);

        foreach (['Reguler', 'Yatim', 'Yatim Piatu', 'Anak Guru', 'Anak Karyawan', 'Kurang Mampu'] as $name) {
            $this->assertDatabaseHas('child_statuses', ['name' => $name, 'is_active' => true]);
        }
    }

    public function test_admin_manages_statuses_and_panitia_is_forbidden(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.child-statuses.store'), ['name' => 'Anak Yatim Uji', 'is_active' => '1'])
            ->assertRedirect(route('admin.child-statuses.index'));
        $created = ChildStatus::where('name', 'Anak Yatim Uji')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.child-statuses.store'), ['name' => 'Yatim'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->put(route('admin.child-statuses.update', $created), ['name' => 'Anak Yatim Uji 2'])
            ->assertRedirect(route('admin.child-statuses.index'));
        $this->assertSame('Anak Yatim Uji 2', $created->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin.child-statuses.destroy', $created))
            ->assertRedirect(route('admin.child-statuses.index'));
        $this->assertDatabaseMissing('child_statuses', ['id' => $created->id]);

        $panitia = $this->panitiaFor($this->scenario()['unit']);
        $this->actingAs($panitia)->get(route('admin.child-statuses.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------
    // A2 — status on the application
    // ---------------------------------------------------------------

    public function test_guardian_chooses_status_at_biodata_and_inactive_is_rejected(): void
    {
        $application = $this->startedApplication($this->scenario());

        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.form', $application))
            ->assertOk()
            ->assertSee('Status Anak')
            ->assertSee('Yatim');

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload(['child_status_id' => $this->yatim->id]))
            ->assertRedirect(route('guardian.applications.documents', $application));

        $this->assertSame($this->yatim->id, $application->fresh()->child_status_id);
        $this->assertSame('Yatim', $application->fresh()->childStatusLabel());
        $this->assertSame($this->yatim->id, $application->fresh()->effectiveChildStatusId());

        $inactive = ChildStatus::create(['name' => 'Nonaktif Uji', 'is_active' => false]);
        $second = $this->startedApplication($this->scenario());
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $second), $this->biodataPayload(['child_status_id' => $inactive->id]))
            ->assertSessionHasErrors('child_status_id');
        $this->assertNull($second->fresh()->child_status_id);
        $this->assertSame('Reguler', $second->fresh()->childStatusLabel());
        $this->assertSame($this->reguler->id, $second->fresh()->effectiveChildStatusId());
    }

    public function test_committee_corrects_status_and_other_unit_panitia_cannot(): void
    {
        $scenario = $this->scenario();
        $application = $this->startedApplication($scenario);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.applications.child-status', $application), ['child_status_id' => $this->yatim->id])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame($this->yatim->id, $application->fresh()->child_status_id);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Status Anak')
            ->assertSee('Yatim');

        $this->actingAs($admin)
            ->patch(route('admin.applications.child-status', $application), ['child_status_id' => ''])
            ->assertRedirect();
        $this->assertNull($application->fresh()->child_status_id);

        $otherUnit = SchoolUnit::factory()->create(['code' => '99']);
        $panitia = $this->panitiaFor($otherUnit);
        $this->actingAs($panitia)
            ->patch(route('admin.applications.child-status', $application), ['child_status_id' => $this->yatim->id])
            ->assertForbidden();
        $this->assertNull($application->fresh()->child_status_id);
    }

    // ---------------------------------------------------------------
    // A3 — fee amounts differentiated by status
    // ---------------------------------------------------------------

    /**
     * @return array{fee: Fee, track: AdmissionTrack}
     */
    private function feeWithAmounts(string $mode = 'fixed'): array
    {
        $scenario = $this->scenario();
        $component = FeeComponent::factory()->create(['code' => 'SPI-'.uniqid(), 'name' => 'SPI Uji']);
        $fee = Fee::factory()->create([
            'school_unit_id' => $scenario['unit']->id,
            'fee_component_id' => $component->id,
            'mode' => $mode,
        ]);

        return ['fee' => $fee, 'track' => $scenario['track']];
    }

    public function test_resolver_prefers_most_specific_status_scope(): void
    {
        ['fee' => $fee, 'track' => $track] = $this->feeWithAmounts();
        $fees = app(FeeServiceInterface::class);

        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 1000000]); // global
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 500000, 'child_status_id' => $this->yatim->id]); // status
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 750000, 'admission_track_id' => $track->id]); // track
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 250000, 'child_status_id' => $this->yatim->id, 'admission_track_id' => $track->id]); // status+track

        $this->assertSame(250000, $fees->resolveFixedAmount($fee, $track->id, 'L', $this->yatim->id));
        $this->assertSame(500000, $fees->resolveFixedAmount($fee, null, 'L', $this->yatim->id));
        $this->assertSame(750000, $fees->resolveFixedAmount($fee, $track->id, 'L', $this->reguler->id));
        $this->assertSame(1000000, $fees->resolveFixedAmount($fee, null, 'P', $this->reguler->id));
        // No status argument at all: status-scoped rows never leak in.
        $this->assertSame(750000, $fees->resolveFixedAmount($fee, $track->id, 'L'));
    }

    public function test_resolver_filters_choice_tiers_by_status_and_zero_means_free(): void
    {
        ['fee' => $fee] = $this->feeWithAmounts('pilihan');
        $fees = app(FeeServiceInterface::class);

        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 1000000, 'sort_order' => 1]);
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 2000000, 'sort_order' => 2]);
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 0, 'child_status_id' => $this->yatim->id, 'sort_order' => 1]);

        $yatimTiers = $fees->resolveAmounts($fee, null, 'L', $this->yatim->id)->pluck('amount')->all();
        $this->assertSame([0], $yatimTiers);

        $regulerTiers = $fees->resolveAmounts($fee, null, 'L', $this->reguler->id)->pluck('amount')->all();
        $this->assertSame([1000000, 2000000], $regulerTiers);
    }

    public function test_invoice_uses_the_applications_status_amounts(): void
    {
        $scenario = $this->scenario();
        $component = FeeComponent::factory()->create(['code' => 'SPI-INV', 'name' => 'SPI Tagihan']);
        $fee = Fee::factory()->create([
            'school_unit_id' => $scenario['unit']->id,
            'fee_component_id' => $component->id,
            'mode' => 'fixed',
        ]);
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 1000000]);
        FeeAmount::create(['fee_id' => $fee->id, 'amount' => 0, 'child_status_id' => $this->yatim->id]);
        $scenario['wave']->update(['announcement_at' => now()->subDay()]);

        $makePassed = fn (?int $statusId) => Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $scenario['unit']->id,
            'admission_wave_id' => $scenario['wave']->id,
            'admission_track_id' => $scenario['track']->id,
            'gender' => 'L',
            'status' => 'passed',
            'child_status_id' => $statusId,
        ]);

        $invoices = app(InvoiceServiceInterface::class);

        $yatimInvoice = $invoices->createInvoice($makePassed($this->yatim->id), []);
        $this->assertSame(0, (int) $yatimInvoice->items->sum('amount'));

        $regulerInvoice = $invoices->createInvoice($makePassed(null), []);
        $this->assertSame(1000000, (int) $regulerInvoice->items->sum('amount'));
    }
}
