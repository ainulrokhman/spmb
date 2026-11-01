<?php

namespace App\Http\Requests\AdmissionTrack;

use App\Rules\UnitAccessible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdmissionTrackRequest extends FormRequest
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
            'school_unit_id' => ['required', 'exists:school_units,id', new UnitAccessible],
            'name' => ['required', 'string', 'max:255', Rule::unique('admission_tracks', 'name')->where('school_unit_id', $this->input('school_unit_id'))->ignore($this->route('admission_track'))],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
