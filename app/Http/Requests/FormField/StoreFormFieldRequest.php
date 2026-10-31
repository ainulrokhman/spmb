<?php

namespace App\Http\Requests\FormField;

use App\Http\Requests\FormField\Concerns\ValidatesFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreFormFieldRequest extends FormRequest
{
    use ValidatesFormField;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The uniqueness rule for the field key within its school unit.
     */
    protected function keyUniqueRule(): Unique
    {
        return Rule::unique('form_fields', 'key')
            ->where('school_unit_id', $this->input('school_unit_id'));
    }
}
