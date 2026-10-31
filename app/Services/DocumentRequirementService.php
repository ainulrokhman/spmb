<?php

namespace App\Services;

use App\Contracts\DocumentRequirementServiceInterface;
use App\Models\DocumentRequirement;
use App\Models\SchoolUnit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentRequirementService implements DocumentRequirementServiceInterface
{
    /**
     * Get the requirement level of every mapped document type for a unit.
     *
     * @return array<int, string>
     */
    public function requirementMapForUnit(SchoolUnit $schoolUnit): array
    {
        return $schoolUnit->documentRequirements()
            ->pluck('requirement', 'document_type_id')
            ->all();
    }

    /**
     * Replace the document requirements of a unit with the given map.
     */
    public function syncUnitRequirements(SchoolUnit $schoolUnit, array $levels): void
    {
        DB::transaction(function () use ($schoolUnit, $levels) {
            $keptTypeIds = [];

            foreach ($levels as $documentTypeId => $level) {
                if (! in_array($level, DocumentRequirement::LEVELS, true)) {
                    throw new InvalidArgumentException('Tingkat syarat berkas tidak dikenal "'.$level.'".');
                }

                DocumentRequirement::updateOrCreate(
                    [
                        'school_unit_id' => $schoolUnit->id,
                        'document_type_id' => (int) $documentTypeId,
                    ],
                    ['requirement' => $level]
                );

                $keptTypeIds[] = (int) $documentTypeId;
            }

            // Types absent from the map are not used by this unit.
            $schoolUnit->documentRequirements()
                ->whereNotIn('document_type_id', $keptTypeIds)
                ->delete();
        });
    }
}
