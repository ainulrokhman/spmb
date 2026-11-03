<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Testing\TestResponse;
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

    /**
     * Save one component card the way the redesigned page
     * does (one AJAX request per component).
     */
    private function saveCard(SchoolUnit $unit, FeeComponent $component, array $payload): TestResponse
    {
        return $this->actingAs($this->admin())
            ->postJson(route('admin.school-units.fees.card', [$unit, $component]), $payload);
    }

    public function test_admin_can_map_components_with_modes_and_minimum(): void
    {
        $unit = SchoolUnit::factory()->create();
        $spi = FeeComponent::factory()->create(['code' => 'SPI']);
        $infaq = FeeComponent::factory()->create(['code' => 'INFAQ']);

        $this->saveCard($unit, $spi, ['mode' => 'pilihan'])->assertOk();
        $this->saveCard($unit, $infaq, ['mode' => 'bebas', 'min_amount' => 10000])->assertOk();

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

        $this->saveCard($unit, $component, ['mode' => 'fixed', 'min_amount' => 5000])
            ->assertOk();

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

        $this->saveCard($unit, $component, ['mode' => 'unused'])
            ->assertOk();

        $this->assertDatabaseCount('fees', 0);
    }

    public function test_card_save_syncs_amount_rows_in_one_request(): void
    {
        $unit = SchoolUnit::factory()->create();
        $component = FeeComponent::factory()->create(['code' => 'SPI']);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $fee = Fee::factory()->create([
            'school_unit_id' => $unit->id,
            'fee_component_id' => $component->id,
            'mode' => 'fixed',
        ]);
        $kept = FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 100000]);
        $dropped = FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 200000, 'gender' => 'P']);

        // One row updated, the gender row omitted (deleted),
        // and one scoped row added — all in a single save.
        $this->saveCard($unit, $component, [
            'mode' => 'fixed',
            'sync_amounts' => 1,
            'amounts' => [
                ['id' => $kept->id, 'amount' => 150000],
                ['amount' => 300000, 'admission_track_id' => $track->id],
            ],
        ])->assertOk()->assertJsonPath('amounts_count', 2);

        $this->assertSame(150000, $kept->fresh()->amount);
        $this->assertDatabaseMissing('fee_amounts', ['id' => $dropped->id]);
        $this->assertDatabaseHas('fee_amounts', [
            'fee_id' => $fee->id,
            'amount' => 300000,
            'admission_track_id' => $track->id,
        ]);
    }

    public function test_card_save_never_touches_other_components(): void
    {
        $unit = SchoolUnit::factory()->create();
        $spi = FeeComponent::factory()->create(['code' => 'SPI']);
        $sop = FeeComponent::factory()->create(['code' => 'SOP']);
        Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $spi->id, 'mode' => 'fixed']);
        Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $sop->id, 'mode' => 'fixed']);

        $this->saveCard($unit, $spi, ['mode' => 'unused'])->assertOk();

        $this->assertDatabaseCount('fees', 1);
        $this->assertDatabaseHas('fees', ['fee_component_id' => $sop->id]);
    }

    public function test_fixed_card_rejects_duplicate_scope_rows(): void
    {
        $unit = SchoolUnit::factory()->create();
        $component = FeeComponent::factory()->create();

        $this->saveCard($unit, $component, [
            'mode' => 'fixed',
            'sync_amounts' => 1,
            'amounts' => [
                ['amount' => 100000],
                ['amount' => 200000],
            ],
        ])->assertUnprocessable();

        // The failed save must not leave a half-saved card.
        $this->assertDatabaseCount('fees', 0);
        $this->assertDatabaseCount('fee_amounts', 0);
    }

    public function test_card_amount_track_must_belong_to_the_unit(): void
    {
        $unit = SchoolUnit::factory()->create();
        $otherUnit = SchoolUnit::factory()->create();
        $component = FeeComponent::factory()->create();
        $foreignTrack = AdmissionTrack::factory()->create(['school_unit_id' => $otherUnit->id]);

        $this->saveCard($unit, $component, [
            'mode' => 'fixed',
            'amounts' => [
                ['amount' => 100000, 'admission_track_id' => $foreignTrack->id],
            ],
        ])->assertUnprocessable();
    }

    public function test_switching_a_card_to_bebas_keeps_its_amounts(): void
    {
        $unit = SchoolUnit::factory()->create();
        $component = FeeComponent::factory()->create();
        $fee = Fee::factory()->create([
            'school_unit_id' => $unit->id,
            'fee_component_id' => $component->id,
            'mode' => 'fixed',
        ]);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 100000]);

        $this->saveCard($unit, $component, ['mode' => 'bebas', 'min_amount' => 5000])
            ->assertOk();

        $this->assertSame(1, FeeAmount::count());
        $this->assertDatabaseHas('fees', [
            'id' => $fee->id,
            'mode' => 'bebas',
            'min_amount' => 5000,
        ]);
    }
}
