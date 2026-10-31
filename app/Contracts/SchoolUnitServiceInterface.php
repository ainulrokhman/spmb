<?php

namespace App\Contracts;

use App\Models\SchoolUnit;

interface SchoolUnitServiceInterface
{
    /**
     * Store a newly created school unit.
     */
    public function createSchoolUnit(array $data): SchoolUnit;

    /**
     * Update the specified school unit.
     */
    public function updateSchoolUnit(SchoolUnit $schoolUnit, array $data): SchoolUnit;

    /**
     * Delete the specified school unit.
     */
    public function deleteSchoolUnit(SchoolUnit $schoolUnit): bool;
}
