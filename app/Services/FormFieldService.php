<?php

namespace App\Services;

use App\Contracts\FormFieldServiceInterface;
use App\Models\FormField;
use InvalidArgumentException;

class FormFieldService implements FormFieldServiceInterface
{
    /**
     * Store a newly created form field.
     */
    public function createFormField(array $data): FormField
    {
        return FormField::create($this->fieldAttributes($data));
    }

    /**
     * Update the specified form field.
     */
    public function updateFormField(FormField $formField, array $data): FormField
    {
        $formField->update($this->fieldAttributes($data));

        return $formField;
    }

    /**
     * Delete the specified form field.
     */
    public function deleteFormField(FormField $formField): bool
    {
        return (bool) $formField->delete();
    }

    /**
     * Parse an options textarea (one option per line) into a list.
     *
     * @return array<int, string>
     */
    public function parseOptions(?string $optionsText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $optionsText))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Map validated form data to model attributes.
     *
     * Choice types (select, radio, checkbox) must carry at least one
     * option; other types never store options.
     */
    private function fieldAttributes(array $data): array
    {
        $isChoice = in_array($data['type'], FormField::CHOICE_TYPES, true);
        $options = $isChoice ? $this->parseOptions($data['options_text'] ?? null) : [];

        if ($isChoice && $options === []) {
            throw new InvalidArgumentException('Choice fields (select, radio, checkbox) need at least one option.');
        }

        return [
            'school_unit_id' => $data['school_unit_id'],
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $isChoice ? $options : null,
            'is_required' => ! empty($data['is_required']),
            'section' => $data['section'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'help_text' => $data['help_text'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
