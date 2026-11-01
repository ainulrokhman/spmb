<?php

namespace App\Http\Requests\Guardian;

use App\Models\Application;
use App\Models\FormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApplicationFormRequest extends FormRequest
{
    /**
     * Only the owning guardian may save the form.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof Application
            && $application->user_id === $this->user()?->id;
    }

    /**
     * Validation rules: fixed core identity, the three parent
     * identities, and the unit's dynamic form fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'child_name' => ['required', 'string', 'max:255'],
            'birth_place' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'guardian_relation' => ['required', Rule::in(array_keys(Application::GUARDIAN_RELATIONS))],
            'has_guardian' => ['nullable', 'boolean'],
        ];

        foreach (['father', 'mother'] as $prefix) {
            $rules += $this->parentRules($prefix, withStatus: true);
        }

        $rules += $this->parentRules('guardian', withStatus: false);
        $rules['guardian.name'] = [$this->guardianRequired(), 'string', 'max:255'];
        $rules['guardian.relation_to_child'] = [$this->guardianRequired(), 'string', 'max:255'];

        /** @var Application $application */
        $application = $this->route('application');

        foreach ($application->schoolUnit->formFields()->where('is_active', true)->get() as $field) {
            $rules['answers.'.$field->key] = $this->dynamicRules($field);

            if ($field->type === 'checkbox') {
                $rules['answers.'.$field->key.'.*'] = [Rule::in($field->options ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Friendly attribute names so dynamic field errors carry the
     * field's label.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'child_name' => 'nama lengkap anak',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'parent_name' => 'nama orang tua',
            'parent_phone' => 'nomor HP orang tua',
            'guardian_relation' => 'hubungan pendaftar',
            'father.name' => 'nama ayah',
            'mother.name' => 'nama ibu',
            'guardian.name' => 'nama wali',
            'guardian.relation_to_child' => 'hubungan wali dengan anak',
        ];

        /** @var Application $application */
        $application = $this->route('application');

        foreach ($application->schoolUnit->formFields()->where('is_active', true)->get() as $field) {
            $attributes['answers.'.$field->key] = $field->label;
        }

        return $attributes;
    }

    /**
     * Rules shared by the father/mother/guardian identity sections.
     *
     * @return array<string, mixed>
     */
    private function parentRules(string $prefix, bool $withStatus): array
    {
        $required = $withStatus ? 'required' : 'nullable';

        $rules = [
            $prefix.'.name' => [$withStatus ? 'required' : 'nullable', 'string', 'max:255'],
            $prefix.'.nik' => ['nullable', 'digits:16'],
            $prefix.'.job' => ['nullable', 'string', 'max:255'],
            $prefix.'.education' => ['nullable', Rule::in(Application::EDUCATION_LEVELS)],
            $prefix.'.income' => ['nullable', Rule::in(Application::INCOME_RANGES)],
            $prefix.'.phone' => ['nullable', 'string', 'max:20'],
            $prefix.'.address' => ['nullable', 'string', 'max:500'],
        ];

        if ($withStatus) {
            $rules[$prefix.'.status'] = ['required', Rule::in(Application::PARENT_STATUSES)];
        }

        return $rules;
    }

    /**
     * Guardian identity fields are required only when the child has
     * a guardian (the has_guardian checkbox).
     */
    private function guardianRequired(): string
    {
        return $this->boolean('has_guardian') ? 'required' : 'nullable';
    }

    /**
     * Rules for one dynamic form field, by its type. File fields are
     * validated as uploads here; their required-ness is enforced
     * after already-stored answers are merged in.
     *
     * @return list<mixed>
     */
    private function dynamicRules(FormField $field): array
    {
        $presence = $field->is_required ? 'required' : 'nullable';

        return match ($field->type) {
            'number' => [$presence, 'numeric'],
            'date' => [$presence, 'date'],
            'select', 'radio' => [$presence, Rule::in($field->options ?? [])],
            'checkbox' => [$presence, 'array'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            default => [$presence, 'string', 'max:2000'],
        };
    }
}
