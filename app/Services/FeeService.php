<?php

namespace App\Services;

use App\Contracts\FeeServiceInterface;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeeService implements FeeServiceInterface
{
    /**
     * Sync a unit's fee mappings (components not present are removed).
     *
     * @param  array<int, array{mode: string, min_amount: int|null}>  $mappings  keyed by fee component id
     */
    public function syncUnitFees(SchoolUnit $unit, array $mappings): void
    {
        DB::transaction(function () use ($unit, $mappings) {
            $unit->fees()
                ->whereNotIn('fee_component_id', array_keys($mappings))
                ->delete();

            foreach ($mappings as $feeComponentId => $mapping) {
                $unit->fees()->updateOrCreate(
                    ['fee_component_id' => $feeComponentId],
                    [
                        'mode' => $mapping['mode'],
                        'min_amount' => $mapping['mode'] === Fee::MODE_BEBAS ? ($mapping['min_amount'] ?? null) : null,
                    ]
                );
            }
        });
    }

    /**
     * Sync one component's mapping across many units.
     *
     * @param  array<int, array{mode: string, min_amount: int|null}>  $perUnit
     */
    public function syncComponentFees(FeeComponent $component, array $perUnit): void
    {
        DB::transaction(function () use ($component, $perUnit) {
            foreach ($perUnit as $unitId => $mapping) {
                $fee = Fee::query()
                    ->where('school_unit_id', $unitId)
                    ->where('fee_component_id', $component->id)
                    ->first();

                if (($mapping['mode'] ?? 'unused') === 'unused') {
                    $fee?->delete();

                    continue;
                }

                Fee::updateOrCreate(
                    ['school_unit_id' => $unitId, 'fee_component_id' => $component->id],
                    [
                        'mode' => $mapping['mode'],
                        'min_amount' => $mapping['mode'] === Fee::MODE_BEBAS ? ($mapping['min_amount'] ?? null) : null,
                    ]
                );
            }
        });
    }

    /**
     * Resolve the amounts that apply to a track/gender combination,
     * most specific first.
     */
    public function resolveAmounts(Fee $fee, ?int $admissionTrackId, ?string $gender, ?int $childStatusId = null): Collection
    {
        // An amount applies when every dimension it restricts
        // matches the applicant; among the applicable amounts
        // only the most specific scope level is used.
        $applicable = $fee->amounts->filter(fn (FeeAmount $amount) => ($amount->child_status_id === null || $amount->child_status_id === $childStatusId)
            && ($amount->admission_track_id === null || $amount->admission_track_id === $admissionTrackId)
            && ($amount->gender === null || $amount->gender === $gender));

        if ($applicable->isEmpty()) {
            return collect();
        }

        $topSpecificity = $applicable->max(fn (FeeAmount $amount) => $amount->specificity());

        return $applicable
            ->filter(fn (FeeAmount $amount) => $amount->specificity() === $topSpecificity)
            ->sortBy([['sort_order', 'asc'], ['amount', 'asc']])
            ->values();
    }

    /**
     * Resolve the single applicable amount for a fixed fee, if defined.
     */
    public function resolveFixedAmount(Fee $fee, ?int $admissionTrackId, ?string $gender, ?int $childStatusId = null): ?int
    {
        return $this->resolveAmounts($fee, $admissionTrackId, $gender, $childStatusId)->first()?->amount;
    }
}
