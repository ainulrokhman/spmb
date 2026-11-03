<?php

namespace App\Services;

use App\Contracts\ChildStatusServiceInterface;
use App\Models\ChildStatus;

class ChildStatusService implements ChildStatusServiceInterface
{
    /**
     * Store a newly created program.
     */
    public function createChildStatus(array $data): ChildStatus
    {
        return ChildStatus::create($this->childStatusAttributes($data));
    }

    /**
     * Update the specified program.
     */
    public function updateChildStatus(ChildStatus $childStatus, array $data): ChildStatus
    {
        $childStatus->update($this->childStatusAttributes($data));

        return $childStatus;
    }

    /**
     * Delete the specified program.
     */
    public function deleteChildStatus(ChildStatus $childStatus): bool
    {
        return (bool) $childStatus->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function childStatusAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
