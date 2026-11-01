<?php

namespace App\Http\Requests\SchoolUnit;

use App\Models\SchoolUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolUnitRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:2', 'regex:/^[0-9]{2}$/', 'unique:school_units,code'],
            'level' => ['required', Rule::in(SchoolUnit::LEVELS)],
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')],
            'form_fee' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
