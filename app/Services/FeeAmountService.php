<?php

namespace App\Services;

use App\Contracts\FeeAmountServiceInterface;
use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use InvalidArgumentException;

class FeeAmountService implements FeeAmountServiceInterface
{
    /**
     * Store a newly created fee amount.
     */
    public function createFeeAmount(array $data): FeeAmount
    {
        $attributes = $this->amountAttributes($data);
        $this->guardTrackBelongsToUnit($attributes);
        $this->guardFixedUniqueness($attributes);

        return FeeAmount::create($attributes);
    }

    /**
     * Update the specified fee amount.
     */
    public function updateFeeAmount(FeeAmount $feeAmount, array $data): FeeAmount
    {
        $attributes = $this->amountAttributes($data);
        $this->guardTrackBelongsToUnit($attributes);
        $this->guardFixedUniqueness($attributes, $feeAmount->id);

        $feeAmount->update($attributes);

        return $feeAmount;
    }

    /**
     * Delete the specified fee amount.
     */
    public function deleteFeeAmount(FeeAmount $feeAmount): bool
    {
        return (bool) $feeAmount->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function amountAttributes(array $data): array
    {
        return [
            'fee_id' => $data['fee_id'],
            'admission_track_id' => $data['admission_track_id'] ?? null,
            'gender' => ($data['gender'] ?? null) ?: null,
            'amount' => $data['amount'],
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * An amount may only reference a track of the fee mapping's unit.
     */
    private function guardTrackBelongsToUnit(array $attributes): void
    {
        if (empty($attributes['admission_track_id'])) {
            return;
        }

        $fee = Fee::findOrFail($attributes['fee_id']);
        $track = AdmissionTrack::find($attributes['admission_track_id']);

        if ($track === null || $track->school_unit_id !== $fee->school_unit_id) {
            throw new InvalidArgumentException('The admission track does not belong to the fee mapping\'s school unit.');
        }
    }

    /**
     * A fixed fee resolves to exactly one amount, so each track/gender
     * combination may appear only once. Pilihan fees offer tiers and
     * may repeat a combination on purpose.
     */
    private function guardFixedUniqueness(array $attributes, ?int $ignoreId = null): void
    {
        $fee = Fee::findOrFail($attributes['fee_id']);

        if ($fee->mode !== Fee::MODE_FIXED) {
            return;
        }

        $exists = FeeAmount::where('fee_id', $attributes['fee_id'])
            ->where('admission_track_id', $attributes['admission_track_id'])
            ->where('gender', $attributes['gender'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A fixed fee may only have one amount per track/gender combination.');
        }
    }
}
