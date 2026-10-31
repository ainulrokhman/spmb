<?php

namespace App\Http\Requests\SchoolUnit;

use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequirementsRequest extends FormRequest
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
            'requirements' => ['nullable', 'array', $this->documentTypesExistRule()],
            'requirements.*' => [Rule::in([...DocumentRequirement::LEVELS, 'unused'])],
        ];
    }

    /**
     * Build the rule ensuring every submitted key is a document type id.
     */
    private function documentTypesExistRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $ids = array_map('intval', array_keys(is_array($value) ? $value : []));

            if ($ids !== [] && DocumentType::whereIn('id', $ids)->count() !== count($ids)) {
                $fail('One or more selected document types are invalid.');
            }
        };
    }

    /**
     * The levels to persist: entries the committee marked as used.
     *
     * @return array<int, string>
     */
    public function levelsToSync(): array
    {
        return collect($this->validated('requirements') ?? [])
            ->filter(fn (string $level) => $level !== 'unused')
            ->all();
    }
}
