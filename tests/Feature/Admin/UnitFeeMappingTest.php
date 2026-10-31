<?php

namespace Tests\Feature\Admin;

use App\Models\Fee;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class UnitFeeMappingTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    public function test_admin_can_view_unit_fee_mapping_page(): void
    {
        $unit = SchoolUnit::factory()->create();
        FeeComponent::factory()->create(['name' => 'Komponen Uji']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.school-units.fees.edit', $unit));

        $response->assertOk();
        $response->assertViewIs('admin.school-units.fees');
        $response->assertSee('Komponen Uji');
    }

    public function test_user_without_school_unit_edit_permission_cannot_view_mapping_page(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->get(route('admin.school-units.fees.edit', $unit));

        $response->assertForbidden();
    }

    public function test_admin_can_map_components_with_modes_and_minimum(): void
    {
        $unit = SchoolUnit::factory()->create();
        $spi = FeeComponent::factory()->create(['code' => 'SPI']);
        $infaq = FeeComponent::factory()->create(['code' => 'INFAQ']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.school-units.fees.update', $unit), [
                'mappings' => [
                    $spi->id => ['mode' => 'pilihan'],
                    $infaq->id => ['mode' => 'bebas', 'min_amount' => 10000],
                ],
            ]);

        $response->assertRedirect(route('admin.school-units.index'));
        $this->assertDatabaseHas('fees', [
            'school_unit_id' => $unit->id,
            'fee_component_id' => $spi->id,
            'mode' => 'pilihan',
            'min_amount' => null,
        ]);
        $this->assertDatabaseHas('fees', [
            'school_unit_id' => $unit->id,
            'fee_component_id' => $infaq->id,
            'mode' => 'bebas',
            'min_amount' => 10000,
        ]);
    }

    public function test_min_amount_is_only_kept_for_bebas_mode(): void
    {
        $unit = SchoolUnit::factory()->create();
        $component = FeeComponent::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.school-units.fees.update', $unit), [
                'mappings' => [
                    $component->id => ['mode' => 'fixed', 'min_amount' => 5000],
                ],
            ]);

        $this->assertDatabaseHas('fees', [
            'school_unit_id' => $unit->id,
            'fee_component_id' => $component->id,
            'mode' => 'fixed',
            'min_amount' => null,
        ]);
    }

    public function test_setting_a_component_to_unused_removes_its_mapping(): void
    {
        $unit = SchoolUnit::factory()->create();
        $component = FeeComponent::factory()->create();
        Fee::factory()->create([
            'school_unit_id' => $unit->id,
            'fee_component_id' => $component->id,
            'mode' => 'fixed',
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.school-units.fees.update', $unit), [
                'mappings' => [
                    $component->id => ['mode' => 'unused'],
                ],
            ]);

        $this->assertDatabaseCount('fees', 0);
    }
}
