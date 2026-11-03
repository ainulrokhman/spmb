<?php

namespace App\Http\Requests\Fee;

use App\Models\Fee;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Save one fee component's mode for many units at once (the
 * "Atur per Unit" page on a fee component).
 */
class SyncComponentUnitsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('school-unit-edit') || $this->user()->can('unit-fee-manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'units' => ['nullable', 'array'],
            'units.*.mode' => ['required', Rule::in(array_merge(['unused'], Fee::MODES))],
            'units.*.min_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * The normalized per-unit mappings, limited to units that
     * actually exist.
     *
     * @return array<int, array{mode: string, min_amount: int|null}>
     */
    public function units(): array
    {
        $unitIds = SchoolUnit::pluck('id')->all();

        return collect($this->input('units', []))
            ->filter(fn ($mapping, $unitId) => in_array((int) $unitId, $unitIds, true))
            ->map(fn ($mapping) => [
                'mode' => $mapping['mode'],
                'min_amount' => isset($mapping['min_amount']) && $mapping['min_amount'] !== ''
                    ? (int) $mapping['min_amount']
                    : null,
            ])
            ->all();
    }
}
