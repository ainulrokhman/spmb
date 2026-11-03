<?php

namespace App\Http\Requests\Guardian;

use App\Models\Application;
use App\Models\FormField;
use App\Models\SchoolUnit;
use Illuminate\Contracts\Validation\Validator;
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
     * Drop untouched sibling rows before validation, so a
     * blank repeater row never fails the per-row rules; a row
     * the guardian actually started (a name, an outside school
     * name, or a chosen unit) is kept and fully validated.
     */
    protected function prepareForValidation(): void
    {
        $application = $this->route('application');
        $answers = $this->input('answers');

        if (! $application instanceof Application || ! is_array($answers)) {
            return;
        }

        $siblingKeys = $application->schoolUnit->formFields()
            ->where('type', 'siblings')
            ->pluck('key');

        foreach ($siblingKeys as $key) {
            if (! is_array($answers[$key] ?? null)) {
                continue;
            }

            $answers[$key] = collect($answers[$key])
                ->filter(fn ($row) => is_array($row)
                    && (trim((string) ($row['name'] ?? '')) !== ''
                        || trim((string) ($row['school_name'] ?? '')) !== ''
                        || (is_numeric($row['school'] ?? null))))
                ->values()
                ->all();
        }

        $this->merge(['answers' => $answers]);
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
            'child_status_id' => ['nullable', Rule::exists('child_statuses', 'id')->where('is_active', true)],
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

            if ($field->type === 'siblings') {
                $rules['answers.'.$field->key.'.*.name'] = ['required', 'string', 'max:255'];
                $rules['answers.'.$field->key.'.*.school'] = ['required', Rule::in($this->schoolOptionValues())];
                $rules['answers.'.$field->key.'.*.school_name'] = ['nullable', 'string', 'max:255'];
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
            'child_status_id' => 'status anak',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
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
            $prefix.'.create_account' => ['nullable', 'boolean'],
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
    /**
     * Values a school choice may take: every active school
     * unit of the foundation, or the external-school marker.
     *
     * @return list<int|string>
     */
    private function schoolOptionValues(): array
    {
        return array_merge(
            SchoolUnit::where('is_active', true)->pluck('id')->all(),
            [FormField::EXTERNAL_SCHOOL]
        );
    }

    /**
     * Cross-row checks the flat rules cannot express: a
     * required siblings field needs at least one kept row,
     * and a sibling at an outside school needs its name.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $application = $this->route('application');

            if (! $application instanceof Application) {
                return;
            }

            $fields = $application->schoolUnit->formFields()
                ->where('is_active', true)
                ->where('type', 'siblings')
                ->get();

            foreach ($fields as $field) {
                $rows = $this->input('answers.'.$field->key, []);

                if ($field->is_required && (! is_array($rows) || count($rows) === 0)) {
                    $validator->errors()->add(
                        'answers.'.$field->key,
                        'Bidang '.$field->label.' wajib mencantumkan minimal satu saudara.'
                    );
                }

                foreach ((array) $rows as $index => $row) {
                    if (($row['school'] ?? null) === FormField::EXTERNAL_SCHOOL
                        && trim((string) ($row['school_name'] ?? '')) === '') {
                        $validator->errors()->add(
                            'answers.'.$field->key.'.'.$index.'.school_name',
                            'Nama sekolah di luar yayasan wajib diisi.'
                        );
                    }
                }
            }
        });
    }

    private function dynamicRules(FormField $field): array
    {
        $presence = $field->is_required ? 'required' : 'nullable';

        return match ($field->type) {
            'number' => [$presence, 'numeric'],
            'date' => [$presence, 'date'],
            'select', 'radio' => [$presence, Rule::in($field->options ?? [])],
            'checkbox' => [$presence, 'array'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'school_unit' => [$presence, Rule::in($this->schoolOptionValues())],
            'siblings' => ['nullable', 'array', 'max:10'],
            default => [$presence, 'string', 'max:2000'],
        };
    }
}
