<?php

namespace App\Services;

use App\Contracts\FeeComponentServiceInterface;
use App\Models\FeeComponent;

class FeeComponentService implements FeeComponentServiceInterface
{
    /**
     * Store a newly created fee component.
     */
    public function createFeeComponent(array $data): FeeComponent
    {
        return FeeComponent::create($this->componentAttributes($data));
    }

    /**
     * Update the specified fee component.
     */
    public function updateFeeComponent(FeeComponent $feeComponent, array $data): FeeComponent
    {
        $feeComponent->update($this->componentAttributes($data));

        return $feeComponent;
    }

    /**
     * Delete the specified fee component.
     */
    public function deleteFeeComponent(FeeComponent $feeComponent): bool
    {
        return (bool) $feeComponent->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function componentAttributes(array $data): array
    {
        return [
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
