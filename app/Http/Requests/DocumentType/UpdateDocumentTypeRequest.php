<?php

namespace App\Http\Requests\DocumentType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('document_types', 'name')->ignore($this->route('document_type'))],
            'code' => ['required', 'string', 'max:50', Rule::unique('document_types', 'code')->ignore($this->route('document_type'))],
            'allowed_extensions' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9,\s]+$/'],
            'max_size_kb' => ['required', 'integer', 'min:1', 'max:102400'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
