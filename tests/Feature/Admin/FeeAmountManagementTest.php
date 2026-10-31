<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class FeeAmountManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.fee-amounts.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.fee-amounts.index';
    }

    /**
     * Create a fee mapping with the given mode for a fresh unit.
     */
    private function makeFee(string $mode = 'fixed'): Fee
    {
        return Fee::factory()->create(['mode' => $mode]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    public function test_user_without_permission_cannot_access_fee_amount_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    public function test_admin_can_view_fee_amount_list(): void
    {
        $this->assertAdminCanViewList();
    }

    public function test_admin_can_create_global_amount(): void
    {
        $fee = $this->makeFee();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'amount' => 1500000,
            ]);

        $response->assertRedirect(route('admin.fee-amounts.index'));
        $this->assertDatabaseHas('fee_amounts', [
            'fee_id' => $fee->id,
            'admission_track_id' => null,
            'gender' => null,
            'amount' => 1500000,
        ]);
    }

    public function test_amount_track_must_belong_to_the_fee_unit(): void
    {
        $fee = $this->makeFee();
        $foreignTrack = AdmissionTrack::factory()->create([
            'school_unit_id' => SchoolUnit::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'admission_track_id' => $foreignTrack->id,
                'amount' => 100000,
            ]);

        $response->assertSessionHasErrors('admission_track_id');
        $this->assertDatabaseCount('fee_amounts', 0);
    }

    public function test_fixed_fee_rejects_duplicate_scope(): void
    {
        $fee = $this->makeFee('fixed');
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 100000]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.fee-amounts.store'), [
                'fee_id' => $fee->id,
                'amount' => 200000,
            ]);

        $response->assertRedirect(route('admin.fee-amounts.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('fee_amounts', 1);
    }

    public function test_pilihan_fee_allows_multiple_tiers_at_same_scope(): void
    {
        $fee = $this->makeFee('pilihan');

        foreach ([500000, 1000000, 2000000] as $amount) {
            $this->actingAs($this->admin())
                ->post(route('admin.fee-amounts.store'), [
                    'fee_id' => $fee->id,
                    'amount' => $amount,
                ])
                ->assertRedirect(route('admin.fee-amounts.index'));
        }

        $this->assertDatabaseCount('fee_amounts', 3);
    }

    public function test_admin_can_update_fee_amount(): void
    {
        $amount = FeeAmount::factory()->create(['amount' => 100000]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.fee-amounts.update', $amount), [
                'fee_id' => $amount->fee_id,
                'amount' => 250000,
            ]);

        $response->assertRedirect(route('admin.fee-amounts.index'));
        $this->assertDatabaseHas('fee_amounts', ['id' => $amount->id, 'amount' => 250000]);
    }

    public function test_admin_can_delete_fee_amount(): void
    {
        $amount = FeeAmount::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.fee-amounts.destroy', $amount));

        $response->assertRedirect(route('admin.fee-amounts.index'));
        $this->assertDatabaseMissing('fee_amounts', ['id' => $amount->id]);
    }
}
