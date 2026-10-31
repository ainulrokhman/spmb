<?php

namespace App\Http\Requests\FormField;

use App\Contracts\FormFieldServiceInterface;
use App\Models\FormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormFieldRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('form_fields', 'key')->where('school_unit_id', $this->input('school_unit_id'))->ignore($this->route('form_field'))],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(FormField::TYPES)],
            'options_text' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
            'section' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Ensure choice types carry at least one option.
     *
     * This runs as an after-validation hook because an empty options
     * input arrives as null, which skips ordinary (non-implicit) rules.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! in_array($this->input('type'), FormField::CHOICE_TYPES, true)) {
                return;
            }

            if (app(FormFieldServiceInterface::class)->parseOptions($this->input('options_text')) === []) {
                $validator->errors()->add('options_text', 'Choice fields (select, radio, checkbox) need at least one option, one per line.');
            }
        });
    }
}
