<?php

namespace App\Http\Requests\AdmissionWave;

use App\Models\AdmissionTrack;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionWaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'school_unit_id' => ['required', 'exists:school_units,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:255'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after_or_equal:opens_at'],
            'announcement_at' => ['nullable', 'date'],
            'tracks' => ['nullable', 'array'],
            'tracks.*' => ['integer', $this->trackBelongsToUnitRule()],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Build the rule ensuring each chosen track belongs to the chosen unit.
     */
    private function trackBelongsToUnitRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $unitId = $this->input('school_unit_id');

            if ($unitId && ! AdmissionTrack::where('id', $value)->where('school_unit_id', $unitId)->exists()) {
                $fail('The selected admission track tidak termasuk dalam unit sekolah yang dipilih.');
            }
        };
    }
}
