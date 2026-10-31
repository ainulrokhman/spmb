<?php

namespace App\Services;

use App\Contracts\AcademicYearServiceInterface;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

class AcademicYearService implements AcademicYearServiceInterface
{
    /**
     * Store a newly created academic year.
     *
     * Activating a year deactivates every other year so that exactly
     * one academic year is active at any time.
     */
    public function createAcademicYear(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $academicYear = AcademicYear::create([
                'name' => $data['name'],
                'is_active' => ! empty($data['is_active']),
            ]);

            $this->keepSingleActive($academicYear);

            return $academicYear;
        });
    }

    /**
     * Update the specified academic year.
     */
    public function updateAcademicYear(AcademicYear $academicYear, array $data): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $data) {
            $academicYear->update([
                'name' => $data['name'],
                'is_active' => ! empty($data['is_active']),
            ]);

            $this->keepSingleActive($academicYear);

            return $academicYear;
        });
    }

    /**
     * Delete the specified academic year.
     */
    public function deleteAcademicYear(AcademicYear $academicYear): bool
    {
        return (bool) $academicYear->delete();
    }

    /**
     * Deactivate all other years when the given year is active.
     */
    private function keepSingleActive(AcademicYear $academicYear): void
    {
        if ($academicYear->is_active) {
            AcademicYear::where('id', '!=', $academicYear->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }
    }
}
