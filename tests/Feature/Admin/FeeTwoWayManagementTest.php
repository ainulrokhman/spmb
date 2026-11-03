<?php

namespace Tests\Feature\Admin;

use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 2 of the fee-UX work: fee settings are reachable from
 * both directions — one component's mode across all units
 * ("Atur per Unit"), and inline amounts on a unit's own fee
 * page — plus the "Biaya per Unit" overview entry point.
 */
class FeeTwoWayManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->admin = User::where('email', 'superadmin@example.com')->firstOrFail();
    }

    private function panitiaFor(SchoolUnit $unit): User
    {
        $user = User::factory()->create();
        $user->assignRole('Panitia');
        $user->schoolUnits()->attach($unit->id);

        return $user;
    }

    public function test_component_page_saves_modes_across_units_at_once(): void
    {
        $component = FeeComponent::factory()->create(['name' => 'SPI Uji', 'code' => 'SPIU']);
        $unitA = SchoolUnit::factory()->create(['name' => 'Unit A']);
        $unitB = SchoolUnit::factory()->create(['name' => 'Unit B']);
        Fee::factory()->create(['school_unit_id' => $unitA->id, 'fee_component_id' => $component->id, 'mode' => 'fixed']);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.fee-components.units.update', $component), [
                'units' => [
                    $unitA->id => ['mode' => 'pilihan'],
                    $unitB->id => ['mode' => 'bebas', 'min_amount' => 25000],
                ],
            ]);

        $response->assertRedirect(route('admin.fee-components.units', $component));
        $this->assertDatabaseHas('fees', ['school_unit_id' => $unitA->id, 'fee_component_id' => $component->id, 'mode' => 'pilihan']);
        $this->assertDatabaseHas('fees', ['school_unit_id' => $unitB->id, 'fee_component_id' => $component->id, 'mode' => 'bebas', 'min_amount' => 25000]);

        // Setting a unit back to "unused" removes its mapping.
        $this->actingAs($this->admin)
            ->put(route('admin.fee-components.units.update', $component), [
                'units' => [$unitA->id => ['mode' => 'unused']],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('fees', ['school_unit_id' => $unitA->id, 'fee_component_id' => $component->id]);
        // The other unit's mapping is untouched by that save.
        $this->assertDatabaseHas('fees', ['school_unit_id' => $unitB->id, 'fee_component_id' => $component->id, 'mode' => 'bebas']);
    }

    public function test_component_page_warns_when_an_active_mode_has_no_amounts(): void
    {
        $component = FeeComponent::factory()->create(['name' => 'SPI Uji', 'code' => 'SPIU']);
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Waspada']);
        Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $component->id, 'mode' => 'pilihan']);

        $this->actingAs($this->admin)
            ->get(route('admin.fee-components.units', $component))
            ->assertOk()
            ->assertSee('Unit Waspada')
            ->assertSee('Belum ada nominal');
    }

    public function test_panitia_only_sees_and_saves_assigned_units_on_the_component_page(): void
    {
        $component = FeeComponent::factory()->create(['name' => 'SPI Uji', 'code' => 'SPIU']);
        $own = SchoolUnit::factory()->create(['name' => 'Unit Panitia']);
        $other = SchoolUnit::factory()->create(['name' => 'Unit Lain']);
        $panitia = $this->panitiaFor($own);

        $this->actingAs($panitia)
            ->get(route('admin.fee-components.units', $component))
            ->assertOk()
            ->assertSee('Unit Panitia')
            ->assertDontSee('Unit Lain');

        $this->actingAs($panitia)
            ->put(route('admin.fee-components.units.update', $component), [
                'units' => [$other->id => ['mode' => 'fixed']],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('fees', ['school_unit_id' => $other->id, 'fee_component_id' => $component->id]);
    }

    public function test_unit_fees_overview_lists_units_with_completeness(): void
    {
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Ringkasan']);
        $component = FeeComponent::factory()->create(['name' => 'SOP Uji', 'code' => 'SOPU']);
        Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $component->id, 'mode' => 'fixed']);

        $this->actingAs($this->admin)
            ->get(route('admin.unit-fees.index'))
            ->assertOk()
            ->assertSee('Unit Ringkasan')
            ->assertSee('1 komponen belum ada nominal')
            ->assertSee('Biaya per Unit');
    }

    public function test_inline_amount_add_and_delete_on_the_unit_fee_page_return_there(): void
    {
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Inline']);
        $fee = Fee::factory()->create(['school_unit_id' => $unit->id, 'mode' => 'pilihan']);

        $this->actingAs($this->admin)
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'amount' => 600000,
                'return_unit_id' => $unit->id,
            ])
            ->assertRedirect(route('admin.school-units.fees.edit', $unit));

        $amount = FeeAmount::where('fee_id', $fee->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('admin.school-units.fees.edit', $unit))
            ->assertOk()
            ->assertSee('Rp 600.000')
            ->assertSee('Tambah Baris', false);

        $this->actingAs($this->admin)
            ->delete(route('admin.fee-amounts.destroy', $amount), ['return_unit_id' => $unit->id])
            ->assertRedirect(route('admin.school-units.fees.edit', $unit));

        $this->assertDatabaseMissing('fee_amounts', ['id' => $amount->id]);
    }

    public function test_return_unit_id_of_another_unit_is_ignored(): void
    {
        $unit = SchoolUnit::factory()->create();
        $other = SchoolUnit::factory()->create();
        $fee = Fee::factory()->create(['school_unit_id' => $unit->id, 'mode' => 'fixed']);

        // A return target naming a different unit than the fee's
        // own falls back to the plain Nominal Biaya list.
        $this->actingAs($this->admin)
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'amount' => 100000,
                'return_unit_id' => $other->id,
            ])
            ->assertRedirect(route('admin.fee-amounts.index'));
    }
}
