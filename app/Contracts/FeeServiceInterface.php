<?php

namespace App\Contracts;

use App\Models\Fee;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Support\Collection;

interface FeeServiceInterface
{
    /**
     * Sync a unit's fee mappings (components not present are removed).
     *
     * @param array<int, array{mode: string, min_amount: int|null}> $mappings keyed by fee component id
     */
    public function syncUnitFees(SchoolUnit $unit, array $mappings): void;

    /**
     * Sync one component's mapping across many units (the mirror
     * of syncUnitFees): each entry is a unit id mapped to a mode
     * and optional minimum; mode 'unused' removes that unit's
     * mapping for the component. Other components are untouched.
     *
     * @param  array<int, array{mode: string, min_amount: int|null}>  $perUnit
     */
    public function syncComponentFees(FeeComponent $component, array $perUnit): void;

    /**
     * Resolve the amounts that apply to a track/gender combination,
     * most specific first (track+gender, then track, then gender, then
     * global). Returns every amount at the winning level: exactly one
     * for fixed fees, the offered tiers for pilihan fees.
     */
    public function resolveAmounts(Fee $fee, ?int $admissionTrackId, ?string $gender): Collection;

    /**
     * Resolve the single applicable amount for a fixed fee, if defined.
     */
    public function resolveFixedAmount(Fee $fee, ?int $admissionTrackId, ?string $gender): ?int;
}
