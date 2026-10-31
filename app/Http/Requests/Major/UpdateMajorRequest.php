<?php

namespace App\Http\Requests\Major;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMajorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('majors', 'name')->where('school_unit_id', $this->input('school_unit_id'))->ignore($this->route('major'))],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
