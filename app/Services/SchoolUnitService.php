<?php

namespace App\Services;

use App\Contracts\SchoolUnitServiceInterface;
use App\Models\SchoolUnit;

class SchoolUnitService implements SchoolUnitServiceInterface
{
    /**
     * Store a newly created school unit.
     */
    public function createSchoolUnit(array $data): SchoolUnit
    {
        return SchoolUnit::create($this->unitAttributes($data));
    }

    /**
     * Update the specified school unit.
     */
    public function updateSchoolUnit(SchoolUnit $schoolUnit, array $data): SchoolUnit
    {
        $schoolUnit->update($this->unitAttributes($data));

        return $schoolUnit;
    }

    /**
     * Delete the specified school unit.
     */
    public function deleteSchoolUnit(SchoolUnit $schoolUnit): bool
    {
        return (bool) $schoolUnit->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function unitAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'],
            'level' => $data['level'],
            'program_id' => $data['program_id'],
            'form_fee' => $data['form_fee'] ?? 0,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
