<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FormField;
use App\Models\SchoolUnit;
use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class UnitScopedCommitteeTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private SchoolUnit $unitA;

    private SchoolUnit $unitB;

    private User $panitia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->unitA = SchoolUnit::factory()->create(['name' => 'Unit A', 'code' => '77']);
        $this->unitB = SchoolUnit::factory()->create(['name' => 'Unit B', 'code' => '78']);

        $this->panitia = User::factory()->create(['email' => 'panitia.a@example.com']);
        $this->panitia->assignRole(SystemAccess::PANITIA_ROLE);
        $this->panitia->schoolUnits()->attach($this->unitA);
    }

    public function test_panitia_role_has_unit_permissions_but_not_global_ones(): void
    {
        $role = Role::findByName(SystemAccess::PANITIA_ROLE);
        $names = $role->permissions->pluck('name');

        foreach (['admission-track-create', 'major-edit', 'admission-wave-delete', 'form-field-list',
            'fee-amount-create', 'unit-fee-manage', 'unit-requirement-manage',
            'application-verify', 'invoice-manage', 'form-payment-decide', 'school-unit-list'] as $permission) {
            $this->assertTrue($names->contains($permission), "Panitia lacks {$permission}");
        }

        foreach (['user-list', 'role-list', 'manage-settings', 'school-unit-create', 'school-unit-edit',
            'academic-year-list', ' fee-component-list'] as $permission) {
            $this->assertFalse($names->contains(trim($permission)), "Panitia should not have {$permission}");
        }
    }

    public function test_track_list_only_shows_assigned_unit_rows(): void
    {
        AdmissionTrack::factory()->create(['school_unit_id' => $this->unitA->id, 'name' => 'Jalur A']);
        AdmissionTrack::factory()->create(['school_unit_id' => $this->unitB->id, 'name' => 'Jalur B']);

        $response = $this->actingAs($this->panitia)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.admission-tracks.index'));

        $response->assertOk();
        $response->assertSee('Jalur A', false);
        $response->assertDontSee('Jalur B', false);
    }

    public function test_panitia_creates_track_for_own_unit_but_not_for_another(): void
    {
        $this->actingAs($this->panitia)
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => $this->unitA->id,
                'name' => 'Jalur Prestasi',
            ])
            ->assertRedirect(route('admin.admission-tracks.index'));

        $this->assertDatabaseHas('admission_tracks', ['name' => 'Jalur Prestasi', 'school_unit_id' => $this->unitA->id]);

        $this->actingAs($this->panitia)
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => $this->unitB->id,
                'name' => 'Jalur Titipan',
            ])
            ->assertSessionHasErrors('school_unit_id');

        $this->assertDatabaseMissing('admission_tracks', ['name' => 'Jalur Titipan']);
    }

    public function test_panitia_cannot_edit_or_delete_another_units_track(): void
    {
        $foreign = AdmissionTrack::factory()->create(['school_unit_id' => $this->unitB->id]);
        $own = AdmissionTrack::factory()->create(['school_unit_id' => $this->unitA->id]);

        $this->actingAs($this->panitia)
            ->get(route('admin.admission-tracks.edit', $foreign))
            ->assertForbidden();

        $this->actingAs($this->panitia)
            ->delete(route('admin.admission-tracks.destroy', $foreign))
            ->assertForbidden();

        $this->actingAs($this->panitia)
            ->delete(route('admin.admission-tracks.destroy', $own))
            ->assertRedirect(route('admin.admission-tracks.index'));

        $this->assertDatabaseMissing('admission_tracks', ['id' => $own->id]);
    }

    public function test_form_field_creation_and_preview_are_unit_scoped(): void
    {
        $this->actingAs($this->panitia)
            ->post(route('admin.form-fields.store'), [
                'school_unit_id' => $this->unitB->id,
                'key' => 'bidang_b',
                'label' => 'Bidang B',
                'type' => 'text',
            ])
            ->assertSessionHasErrors('school_unit_id');

        $this->actingAs($this->panitia)
            ->get(route('admin.school-units.form-preview', $this->unitB))
            ->assertForbidden();

        $this->actingAs($this->panitia)
            ->get(route('admin.school-units.form-preview', $this->unitA))
            ->assertOk();
    }

    public function test_wave_creation_for_another_unit_is_rejected(): void
    {
        $year = \App\Models\AcademicYear::factory()->create(['is_active' => true]);

        $this->actingAs($this->panitia)
            ->post(route('admin.admission-waves.store'), [
                'school_unit_id' => $this->unitB->id,
                'academic_year_id' => $year->id,
                'name' => 'Gelombang Titipan',
                'opens_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'closes_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('school_unit_id');

        $this->assertDatabaseMissing('admission_waves', ['name' => 'Gelombang Titipan']);
    }

    public function test_unit_fee_and_requirement_pages_are_unit_scoped(): void
    {
        $this->actingAs($this->panitia)
            ->get(route('admin.school-units.fees.edit', $this->unitA))
            ->assertOk();

        $this->actingAs($this->panitia)
            ->get(route('admin.school-units.fees.edit', $this->unitB))
            ->assertForbidden();

        $this->actingAs($this->panitia)
            ->get(route('admin.school-units.document-requirements.edit', $this->unitA))
            ->assertOk();

        $this->actingAs($this->panitia)
            ->get(route('admin.school-units.document-requirements.edit', $this->unitB))
            ->assertForbidden();
    }

    public function test_fee_amount_creation_checks_the_fees_unit(): void
    {
        $ownFee = Fee::factory()->create(['school_unit_id' => $this->unitA->id, 'mode' => 'fixed']);
        $foreignFee = Fee::factory()->create(['school_unit_id' => $this->unitB->id, 'mode' => 'fixed']);

        $this->actingAs($this->panitia)
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $ownFee->id,
                'amount' => 123000,
            ])
            ->assertRedirect(route('admin.fee-amounts.index'));

        $this->assertDatabaseHas('fee_amounts', ['fee_id' => $ownFee->id, 'amount' => 123000]);

        $this->actingAs($this->panitia)
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $foreignFee->id,
                'amount' => 456000,
            ])
            ->assertSessionHasErrors('fee_id');

        $this->assertDatabaseMissing('fee_amounts', ['fee_id' => $foreignFee->id]);
    }

    public function test_school_unit_list_only_shows_assigned_units(): void
    {
        $response = $this->actingAs($this->panitia)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.school-units.index'));

        $response->assertOk();
        $response->assertSee('Unit A', false);
        $response->assertDontSee('Unit B', false);
    }

    public function test_global_admin_is_not_scoped(): void
    {
        AdmissionTrack::factory()->create(['school_unit_id' => $this->unitB->id, 'name' => 'Jalur B Global']);

        $response = $this->actingAs($this->admin())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.admission-tracks.index'));

        $response->assertSee('Jalur B Global', false);

        $this->actingAs($this->admin())
            ->get(route('admin.school-units.fees.edit', $this->unitB))
            ->assertOk();
    }

    public function test_demo_committee_accounts_use_the_panitia_role(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $smp = User::where('email', 'panitia.smp@example.com')->firstOrFail();
        $smk = User::where('email', 'panitia.smk@example.com')->firstOrFail();

        $this->assertTrue($smp->hasRole(SystemAccess::PANITIA_ROLE));
        $this->assertFalse($smp->hasGlobalUnitAccess());

        $smpUnit = SchoolUnit::where('code', '31')->firstOrFail();
        $smkUnit = SchoolUnit::where('code', '42')->firstOrFail();

        $this->actingAs($smp)
            ->get(route('admin.school-units.fees.edit', $smpUnit))
            ->assertOk();

        $this->actingAs($smp)
            ->get(route('admin.school-units.fees.edit', $smkUnit))
            ->assertForbidden();

        $this->assertTrue($smk->hasRole(SystemAccess::PANITIA_ROLE));
    }
}
