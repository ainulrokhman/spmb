<?php

namespace App\Http\Requests\FeeAmount\Concerns;

use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use Illuminate\Validation\Rule;

trait ValidatesFeeAmount
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'fee_id' => ['required', 'exists:fees,id'],
            'admission_track_id' => ['nullable', 'exists:admission_tracks,id', $this->trackBelongsToFeeUnit()],
            'gender' => ['nullable', Rule::in(array_keys(FeeAmount::GENDERS))],
            'amount' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Build the rule ensuring the track belongs to the fee's unit.
     */
    private function trackBelongsToFeeUnit(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $fee = Fee::find($this->input('fee_id'));
            $track = AdmissionTrack::find($value);

            if ($fee !== null && $track !== null && $track->school_unit_id !== $fee->school_unit_id) {
                $fail('The admission track does not belong to the fee mapping\'s school unit.');
            }
        };
    }
}
