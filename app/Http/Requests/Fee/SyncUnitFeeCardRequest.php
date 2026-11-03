<?php

namespace App\Http\Requests\Fee;

use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUnitFeeCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * (The controller aborts for users without the fee
     * permissions, mirroring the page itself.)
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request: one
     * component card — its mapping plus its amount rows.
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in([...Fee::MODES, 'unused'])],
            'min_amount' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'amounts' => ['nullable', 'array'],
            'amounts.*.id' => ['nullable', 'integer'],
            'amounts.*.amount' => ['required', 'integer', 'min:0'],
            'amounts.*.admission_track_id' => ['nullable', 'exists:admission_tracks,id', $this->trackBelongsToUnit()],
            'amounts.*.gender' => ['nullable', Rule::in(array_keys(FeeAmount::GENDERS))],
            'amounts.*.child_status_id' => ['nullable', 'exists:child_statuses,id'],
            'amounts.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * The card's mapping, normalized like one entry of the
     * former bulk sync.
     *
     * @return array{mode: string, min_amount: int|null, is_active: bool}
     */
    public function mapping(): array
    {
        return [
            'mode' => (string) $this->input('mode'),
            'min_amount' => $this->filled('min_amount') ? (int) $this->input('min_amount') : null,
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * The card's amount rows, normalized for the service.
     *
     * @return list<array{id: int|null, amount: int, admission_track_id: int|null, gender: string|null, child_status_id: int|null, sort_order: int}>
     */
    public function amountRows(): array
    {
        return collect($this->input('amounts', []))
            ->map(fn ($row) => [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'amount' => (int) ($row['amount'] ?? 0),
                'admission_track_id' => isset($row['admission_track_id']) && $row['admission_track_id'] !== '' ? (int) $row['admission_track_id'] : null,
                'gender' => ($row['gender'] ?? '') !== '' ? (string) $row['gender'] : null,
                'child_status_id' => isset($row['child_status_id']) && $row['child_status_id'] !== '' ? (int) $row['child_status_id'] : null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Build the rule ensuring an amount row's track belongs
     * to the unit whose card is being saved.
     */
    private function trackBelongsToUnit(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $unit = $this->route('school_unit');
            $track = AdmissionTrack::find($value);

            if ($unit !== null && $track !== null && $track->school_unit_id !== $unit->id) {
                $fail('Jalur tidak termasuk dalam unit sekolah ini.');
            }
        };
    }
}
