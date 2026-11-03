<?php

namespace App\Contracts;

use App\Models\Fee;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Support\Collection;

interface FeeServiceInterface
{
    /**
     * Sync one component's mapping across many units (used by
     * the per-component "Atur per Unit" page): each entry is a
     * unit id mapped to a mode and optional minimum; mode
     * 'unused' removes that unit's mapping for the component.
     * Other components are untouched.
     *
     * @param  array<int, array{mode: string, min_amount: int|null}>  $perUnit
     */
    public function syncComponentFees(FeeComponent $component, array $perUnit): void;

    /**
     * Save ONE component card of a unit's fee page: the mapping
     * (mode / minimum / active switch) together with its amount
     * rows (a null amount set leaves the rows untouched — the
     * card form always sends its rows explicitly). Mode
     * 'unused' removes only this component's mapping;
     * other components are never touched. Amount rows are
     * synced for the fixed and pilihan modes (rows carrying an
     * id are updated, new rows created, missing rows deleted);
     * for bebas the stored amounts are kept untouched. Returns
     * the saved mapping (with amounts loaded), or null when the
     * mapping was removed.
     *
     * @param  array{mode: string, min_amount: int|null, is_active: bool}  $mapping
     * @param  list<array{id: int|null, amount: int, admission_track_id: int|null, gender: string|null, child_status_id: int|null, sort_order: int}>  $amounts
     */
    public function syncUnitFeeCard(SchoolUnit $unit, FeeComponent $component, array $mapping, ?array $amounts): ?Fee;

    /**
     * Resolve the amounts that apply to a track/gender combination,
     * most specific first (track+gender, then track, then gender, then
     * global). Returns every amount at the winning level: exactly one
     * for fixed fees, the offered tiers for pilihan fees.
     */
    public function resolveAmounts(Fee $fee, ?int $admissionTrackId, ?string $gender, ?int $childStatusId = null): Collection;

    /**
     * Resolve the single applicable amount for a fixed fee, if defined.
     */
    public function resolveFixedAmount(Fee $fee, ?int $admissionTrackId, ?string $gender, ?int $childStatusId = null): ?int;
}
