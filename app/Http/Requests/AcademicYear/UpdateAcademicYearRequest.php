<?php

namespace App\Http\Requests\AcademicYear;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('academic_years', 'name')->ignore($this->route('academic_year'))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
