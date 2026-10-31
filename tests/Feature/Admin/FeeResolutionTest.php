<?php

namespace Tests\Feature\Admin;

use App\Contracts\FeeServiceInterface;
use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A fixed fee with amounts at every specificity level.
     *
     * @return array{0: Fee, 1: AdmissionTrack}
     */
    private function feeWithAllLevels(): array
    {
        $unit = SchoolUnit::factory()->create();
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $fee = Fee::factory()->create(['school_unit_id' => $unit->id, 'mode' => 'fixed']);

        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 1000]);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'gender' => 'L', 'amount' => 900]);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'admission_track_id' => $track->id, 'amount' => 800]);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'admission_track_id' => $track->id, 'gender' => 'L', 'amount' => 700]);

        return [$fee->fresh(), $track];
    }

    private function resolver(): FeeServiceInterface
    {
        return app(FeeServiceInterface::class);
    }

    public function test_track_and_gender_amount_wins_over_all(): void
    {
        [$fee, $track] = $this->feeWithAllLevels();

        $this->assertSame(700, $this->resolver()->resolveFixedAmount($fee, $track->id, 'L'));
    }

    public function test_track_amount_wins_over_gender_and_global(): void
    {
        [$fee, $track] = $this->feeWithAllLevels();

        $this->assertSame(800, $this->resolver()->resolveFixedAmount($fee, $track->id, 'P'));
    }

    public function test_gender_amount_wins_over_global(): void
    {
        [$fee] = $this->feeWithAllLevels();

        $this->assertSame(900, $this->resolver()->resolveFixedAmount($fee, null, 'L'));
    }

    public function test_global_amount_is_the_fallback(): void
    {
        [$fee] = $this->feeWithAllLevels();

        $this->assertSame(1000, $this->resolver()->resolveFixedAmount($fee, null, 'P'));
        $this->assertSame(1000, $this->resolver()->resolveFixedAmount($fee, null, null));
    }

    public function test_pilihan_resolution_returns_all_tiers_of_the_winning_level(): void
    {
        $unit = SchoolUnit::factory()->create();
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $fee = Fee::factory()->create(['school_unit_id' => $unit->id, 'mode' => 'pilihan']);

        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 500000, 'sort_order' => 1]);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 1000000, 'sort_order' => 2]);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'admission_track_id' => $track->id, 'amount' => 750000]);

        $globalTiers = $this->resolver()->resolveAmounts($fee, null, null);
        $this->assertSame([500000, 1000000], $globalTiers->pluck('amount')->all());

        $trackTiers = $this->resolver()->resolveAmounts($fee, $track->id, null);
        $this->assertSame([750000], $trackTiers->pluck('amount')->all());
    }

    public function test_resolution_is_empty_when_nothing_is_defined(): void
    {
        $fee = Fee::factory()->create(['mode' => 'fixed']);

        $this->assertNull($this->resolver()->resolveFixedAmount($fee, null, null));
        $this->assertTrue($this->resolver()->resolveAmounts($fee, null, null)->isEmpty());
    }
}
