<?php

namespace App\Services;

use App\Contracts\FeeServiceInterface;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeeService implements FeeServiceInterface
{
    /**
     * Save ONE component card of a unit's fee page (see the
     * interface for the contract). Runs in one transaction so
     * a rejected amount set never leaves a half-saved card.
     *
     * @param  array{mode: string, min_amount: int|null, is_active: bool}  $mapping
     * @param  list<array{id: int|null, amount: int, admission_track_id: int|null, gender: string|null, child_status_id: int|null, sort_order: int}>  $amounts
     */
    public function syncUnitFeeCard(SchoolUnit $unit, FeeComponent $component, array $mapping, ?array $amounts): ?Fee
    {
        return DB::transaction(function () use ($unit, $component, $mapping, $amounts) {
            $fee = $unit->fees()->where('fee_component_id', $component->id)->first();

            if ($mapping['mode'] === 'unused') {
                $fee?->delete();

                return null;
            }

            $fee ??= new Fee([
                'school_unit_id' => $unit->id,
                'fee_component_id' => $component->id,
            ]);
            $fee->fill([
                'mode' => $mapping['mode'],
                'min_amount' => $mapping['mode'] === Fee::MODE_BEBAS ? $mapping['min_amount'] : null,
                'is_active' => $mapping['is_active'],
            ])->save();

            if ($amounts !== null && in_array($mapping['mode'], [Fee::MODE_FIXED, Fee::MODE_PILIHAN], true)) {
                $this->syncCardAmounts($fee, $amounts);
            }

            return $fee->load('amounts');
        });
    }

    /**
     * Replace a mapping's amount rows with the submitted set:
     * rows carrying an id of this mapping are updated, the
     * rest are created, and rows missing from the submission
     * are deleted. Fixed mappings may not repeat a scope
     * combination; pilihan mappings may (each row is a tier).
     *
     * @param  list<array{id: int|null, amount: int, admission_track_id: int|null, gender: string|null, child_status_id: int|null, sort_order: int}>  $amounts
     */
    private function syncCardAmounts(Fee $fee, array $amounts): void
    {
        if ($fee->mode === Fee::MODE_FIXED) {
            $seen = [];

            foreach ($amounts as $row) {
                $key = ($row['admission_track_id'] ?? '').'|'.($row['gender'] ?? '').'|'.($row['child_status_id'] ?? '');

                if (isset($seen[$key])) {
                    throw new InvalidArgumentException('Kombinasi jalur, jender, dan status anak yang sama tidak boleh berulang untuk mode Tetap.');
                }

                $seen[$key] = true;
            }
        }

        $keptIds = [];

        foreach ($amounts as $row) {
            $data = [
                'amount' => $row['amount'],
                'admission_track_id' => $row['admission_track_id'],
                'gender' => $row['gender'],
                'child_status_id' => $row['child_status_id'],
                'sort_order' => $row['sort_order'],
            ];

            $existing = ! empty($row['id'])
                ? $fee->amounts()->where('id', $row['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($data);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $fee->amounts()->create($data)->id;
            }
        }

        $fee->amounts()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Sync one component's mapping across many units.
     *
     * @param  array<int, array{mode: string, min_amount: int|null, is_active: bool}>  $perUnit
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
                        'is_active' => $mapping['is_active'] ?? true,
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
