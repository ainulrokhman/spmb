<?php

namespace App\Http\Requests\Fee;

use App\Models\Fee;
use App\Models\FeeComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUnitFeesRequest extends FormRequest
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
            'mappings' => ['nullable', 'array'],
            'mappings.*.mode' => ['required', Rule::in([...Fee::MODES, 'unused'])],
            'mappings.*.min_amount' => ['nullable', 'integer', 'min:0'],
            'mappings.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The fee mappings keyed by component id, limited to components
     * that actually exist.
     *
     * @return array<int, array{mode: string, min_amount: int|null, is_active: bool}>
     */
    public function mappings(): array
    {
        $componentIds = FeeComponent::pluck('id')->all();

        return collect($this->input('mappings', []))
            ->filter(fn ($mapping, $componentId) => in_array((int) $componentId, $componentIds, true)
                && ($mapping['mode'] ?? 'unused') !== 'unused')
            ->map(fn ($mapping) => [
                'mode' => $mapping['mode'],
                'min_amount' => isset($mapping['min_amount']) && $mapping['min_amount'] !== ''
                    ? (int) $mapping['min_amount']
                    : null,
                'is_active' => filter_var($mapping['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ])
            ->all();
    }
}
