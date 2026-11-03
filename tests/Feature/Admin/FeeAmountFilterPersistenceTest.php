<?php

namespace Tests\Feature\Admin;

use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 1 of the persistent-filters work: on the Nominal Biaya
 * list, the unit + component filters survive the round trip —
 * the create form is prefilled from them, row actions carry
 * them, and store/update/delete redirect back to the same
 * filtered list instead of a bare one.
 */
class FeeAmountFilterPersistenceTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->admin = User::where('email', 'superadmin@example.com')->firstOrFail();
    }

    private function filteredIndexUrl(Fee $fee): string
    {
        return route('admin.fee-amounts.index').'?'.http_build_query([
            'school_unit_id' => $fee->school_unit_id,
            'fee_component_id' => $fee->fee_component_id,
        ]);
    }

    private function returnFields(Fee $fee): array
    {
        return [
            'return_school_unit_id' => $fee->school_unit_id,
            'return_fee_component_id' => $fee->fee_component_id,
        ];
    }

    public function test_store_redirects_back_to_the_filtered_list(): void
    {
        $fee = Fee::factory()->create(['mode' => 'fixed']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.fee-amounts.store'), array_merge([
                'fee_id' => $fee->id,
                'amount' => 1500000,
            ], $this->returnFields($fee)));

        $response->assertRedirect($this->filteredIndexUrl($fee));
    }

    public function test_update_and_delete_redirect_back_to_the_filtered_list(): void
    {
        $fee = Fee::factory()->create(['mode' => 'fixed']);
        $amount = FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 1000000]);

        $this->actingAs($this->admin)
            ->put(route('admin.fee-amounts.update', $amount), array_merge([
                'fee_id' => $fee->id,
                'amount' => 1250000,
            ], $this->returnFields($fee)))
            ->assertRedirect($this->filteredIndexUrl($fee));

        $this->actingAs($this->admin)
            ->delete(route('admin.fee-amounts.destroy', $amount), $this->returnFields($fee))
            ->assertRedirect($this->filteredIndexUrl($fee));
    }

    public function test_store_without_return_fields_still_redirects_to_the_plain_list(): void
    {
        $fee = Fee::factory()->create(['mode' => 'fixed']);

        $this->actingAs($this->admin)
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'amount' => 1500000,
            ])
            ->assertRedirect(route('admin.fee-amounts.index'));
    }

    public function test_create_form_preselects_the_fee_matching_the_filters(): void
    {
        $fee = Fee::factory()->create(['mode' => 'pilihan']);
        Fee::factory()->create(['mode' => 'fixed']); // another mapping, must not be selected

        $response = $this->actingAs($this->admin)
            ->get(route('admin.fee-amounts.create').'?'.http_build_query([
                'school_unit_id' => $fee->school_unit_id,
                'fee_component_id' => $fee->fee_component_id,
            ]));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<option value="'.$fee->id.'"[^>]*selected/',
            $response->getContent()
        );
        // The return filters ride along as hidden fields.
        $response->assertSee('name="return_school_unit_id" value="'.$fee->school_unit_id.'"', false);
        $response->assertSee('name="return_fee_component_id" value="'.$fee->fee_component_id.'"', false);
    }

    public function test_datatable_row_actions_carry_the_active_filters(): void
    {
        $fee = Fee::factory()->create(['mode' => 'fixed']);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 1000000]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.fee-amounts.index').'?'.http_build_query([
                'school_unit_id' => $fee->school_unit_id,
                'fee_component_id' => $fee->fee_component_id,
            ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $actions = $response->json('data.0.actions');
        $this->assertStringContainsString('school_unit_id='.$fee->school_unit_id, $actions);
        $this->assertStringContainsString('name="return_school_unit_id" value="'.$fee->school_unit_id.'"', $actions);
        $this->assertStringContainsString('name="return_fee_component_id" value="'.$fee->fee_component_id.'"', $actions);
    }

    public function test_crafted_return_values_cannot_inject_other_parameters(): void
    {
        $fee = Fee::factory()->create(['mode' => 'fixed']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'amount' => 1500000,
                'return_school_unit_id' => '2 OR 1=1',
                'return_fee_component_id' => -5,
            ]);

        $response->assertRedirect(route('admin.fee-amounts.index'));
    }
}
