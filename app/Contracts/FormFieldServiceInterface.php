<?php

namespace App\Contracts;

use App\Models\FormField;

interface FormFieldServiceInterface
{
    /**
     * Store a newly created form field.
     */
    public function createFormField(array $data): FormField;

    /**
     * Update the specified form field.
     */
    public function updateFormField(FormField $formField, array $data): FormField;

    /**
     * Delete the specified form field.
     */
    public function deleteFormField(FormField $formField): bool;

    /**
     * Parse an options textarea (one option per line) into a list.
     *
     * @return array<int, string>
     */
    public function parseOptions(?string $optionsText): array;
}
