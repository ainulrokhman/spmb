<?php

namespace App\Contracts;

use App\Models\SchoolUnit;

interface DocumentRequirementServiceInterface
{
    /**
     * Get the requirement level of every document type for a unit.
     *
     * @return array<int, string> Map of document type id to requirement level.
     */
    public function requirementMapForUnit(SchoolUnit $schoolUnit): array;

    /**
     * Replace the document requirements of a unit with the given map.
     *
     * @param  array<int, string>  $levels  Map of document type id to level
     *                                      ('required' or 'optional'); types
     *                                      missing from the map are unused.
     */
    public function syncUnitRequirements(SchoolUnit $schoolUnit, array $levels): void;
}
