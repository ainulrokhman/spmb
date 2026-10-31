<?php

namespace App\Contracts;

use App\Models\AcademicYear;

interface AcademicYearServiceInterface
{
    /**
     * Store a newly created academic year.
     */
    public function createAcademicYear(array $data): AcademicYear;

    /**
     * Update the specified academic year.
     */
    public function updateAcademicYear(AcademicYear $academicYear, array $data): AcademicYear;

    /**
     * Delete the specified academic year.
     */
    public function deleteAcademicYear(AcademicYear $academicYear): bool;
}
