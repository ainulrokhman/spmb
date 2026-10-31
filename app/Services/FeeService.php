<?php

namespace App\Services;

use App\Contracts\FeeServiceInterface;
use App\Models\Fee;
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
     * Resolve the amounts that apply to a track/gender combination,
     * most specific first.
     */
    public function resolveAmounts(Fee $fee, ?int $admissionTrackId, ?string $gender): Collection
    {
        $amounts = $fee->amounts;

        $levels = [
            fn ($amount) => $admissionTrackId !== null && $gender !== null
                && $amount->admission_track_id === $admissionTrackId && $amount->gender === $gender,
            fn ($amount) => $admissionTrackId !== null
                && $amount->admission_track_id === $admissionTrackId && $amount->gender === null,
            fn ($amount) => $gender !== null
                && $amount->admission_track_id === null && $amount->gender === $gender,
            fn ($amount) => $amount->admission_track_id === null && $amount->gender === null,
        ];

        foreach ($levels as $matches) {
            $resolved = $amounts->filter($matches)
                ->sortBy([['sort_order', 'asc'], ['amount', 'asc']])
                ->values();

            if ($resolved->isNotEmpty()) {
                return $resolved;
            }
        }

        return collect();
    }

    /**
     * Resolve the single applicable amount for a fixed fee, if defined.
     */
    public function resolveFixedAmount(Fee $fee, ?int $admissionTrackId, ?string $gender): ?int
    {
        return $this->resolveAmounts($fee, $admissionTrackId, $gender)->first()?->amount;
    }
}
