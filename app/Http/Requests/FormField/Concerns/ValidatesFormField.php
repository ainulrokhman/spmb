<?php

namespace App\Http\Requests\FormField\Concerns;

use App\Rules\UnitAccessible;
use App\Contracts\FormFieldServiceInterface;
use App\Models\FormField;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

trait ValidatesFormField
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'school_unit_id' => ['required', 'exists:school_units,id', new UnitAccessible],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', $this->keyUniqueRule()],
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
     * The uniqueness rule for the field key within its school unit.
     */
    abstract protected function keyUniqueRule(): Unique;

    /**
     * Ensure choice types carry at least one option.
     *
     * This runs as an after-validation hook because an empty options
     * input arrives as null, which skips ordinary (non-implicit) rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! in_array($this->input('type'), FormField::CHOICE_TYPES, true)) {
                return;
            }

            if (app(FormFieldServiceInterface::class)->parseOptions($this->input('options_text')) === []) {
                $validator->errors()->add('options_text', 'Bidang pilihan (select, radio, checkbox) wajib memiliki minimal satu pilihan, satu per baris.');
            }
        });
    }
}
