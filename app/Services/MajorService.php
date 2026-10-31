<?php

namespace App\Services;

use App\Contracts\MajorServiceInterface;
use App\Models\Major;

class MajorService implements MajorServiceInterface
{
    /**
     * Store a newly created major.
     */
    public function createMajor(array $data): Major
    {
        return Major::create($this->majorAttributes($data));
    }

    /**
     * Update the specified major.
     */
    public function updateMajor(Major $major, array $data): Major
    {
        $major->update($this->majorAttributes($data));

        return $major;
    }

    /**
     * Delete the specified major.
     */
    public function deleteMajor(Major $major): bool
    {
        return (bool) $major->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function majorAttributes(array $data): array
    {
        return [
            'school_unit_id' => $data['school_unit_id'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
