<?php

namespace Tests\Feature\Admin;

use App\Contracts\InvoiceServiceInterface;
use App\Models\Application;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Paket A: a fee component can be switched off per unit
 * (its amounts are kept but it is never billed), the fee
 * amount table leads with the component name, and the unit
 * fee page previews a sample invoice.
 */
class FeeActivationTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    /**
     * A unit with two fixed components (SPI Rp 500.000 and
     * SOP Rp 300.000), both active with a global amount.
     *
     * @return array{unit: SchoolUnit, spi: FeeComponent, sop: FeeComponent}
     */
    private function scenario(): array
    {
        $unit = SchoolUnit::factory()->create();
        $spi = FeeComponent::factory()->create(['code' => 'SPI', 'name' => 'Sumbangan Pengembangan']);
        $sop = FeeComponent::factory()->create(['code' => 'SOP', 'name' => 'Sumbangan Operasional']);

        foreach ([[$spi, 500000], [$sop, 300000]] as [$component, $amount]) {
            $fee = Fee::factory()->create([
                'school_unit_id' => $unit->id,
                'fee_component_id' => $component->id,
                'mode' => 'fixed',
                'is_active' => true,
            ]);
            FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => $amount]);
        }

        return ['unit' => $unit, 'spi' => $spi, 'sop' => $sop];
    }

    /**
     * @return list<int> fee component ids in the invoice draft
     */
    private function draftComponentIds(SchoolUnit $unit): array
    {
        $application = Application::factory()->create([
            'school_unit_id' => $unit->id,
            'status' => 'passed',
        ]);

        return collect(app(InvoiceServiceInterface::class)->draftLines($application))
            ->map(fn ($line) => $line['fee']->fee_component_id)
            ->all();
    }

    public function test_disabling_a_component_keeps_amounts_but_excludes_it_from_the_invoice_draft(): void
    {
        ['unit' => $unit, 'spi' => $spi, 'sop' => $sop] = $this->scenario();

        $this->assertSame([$spi->id, $sop->id], collect($this->draftComponentIds($unit))->sort()->values()->all());

        $this->actingAs($this->admin())
            ->put(route('admin.school-units.fees.update', $unit), [
                'mappings' => [
                    $spi->id => ['mode' => 'fixed', 'is_active' => '0'],
                    $sop->id => ['mode' => 'fixed', 'is_active' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.school-units.index'));

        $this->assertDatabaseHas('fees', [
            'school_unit_id' => $unit->id,
            'fee_component_id' => $spi->id,
            'is_active' => false,
        ]);
        // The amounts survive the switch-off.
        $this->assertSame(2, FeeAmount::count());
        $this->assertSame([$sop->id], $this->draftComponentIds($unit));

        // Switching it back on restores it to the draft.
        $this->actingAs($this->admin())
            ->put(route('admin.school-units.fees.update', $unit), [
                'mappings' => [
                    $spi->id => ['mode' => 'fixed', 'is_active' => '1'],
                    $sop->id => ['mode' => 'fixed', 'is_active' => '1'],
                ],
            ]);

        $this->assertSame([$spi->id, $sop->id], collect($this->draftComponentIds($unit))->sort()->values()->all());
    }

    public function test_disabling_also_works_from_the_component_page(): void
    {
        ['unit' => $unit, 'spi' => $spi] = $this->scenario();

        $this->actingAs($this->admin())
            ->put(route('admin.fee-components.units.update', $spi), [
                'units' => [
                    $unit->id => ['mode' => 'fixed', 'is_active' => '0'],
                ],
            ])
            ->assertRedirect(route('admin.fee-components.units', $spi));

        $this->assertDatabaseHas('fees', [
            'school_unit_id' => $unit->id,
            'fee_component_id' => $spi->id,
            'is_active' => false,
        ]);
    }

    public function test_fee_amount_table_leads_with_the_component_name(): void
    {
        ['unit' => $unit, 'spi' => $spi] = $this->scenario();

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.fee-amounts.index', ['school_unit_id' => $unit->id]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $mapping = $response->json('data.0.mapping');
        $this->assertStringStartsWith('<span class="fw-semibold text-body">Sumbangan Pengembangan</span>', $mapping);
        $this->assertStringContainsString($unit->name, $mapping);
    }

    public function test_unit_fee_page_previews_the_fixed_total_of_active_components(): void
    {
        ['unit' => $unit, 'spi' => $spi] = $this->scenario();

        $this->actingAs($this->admin())
            ->get(route('admin.school-units.fees.edit', $unit))
            ->assertOk()
            ->assertSee('Contoh Tagihan')
            ->assertSee('Rp 800.000');

        Fee::where('fee_component_id', $spi->id)->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.school-units.fees.edit', $unit))
            ->assertOk()
            ->assertSee('Rp 300.000')
            ->assertDontSee('Rp 800.000');
    }
}
