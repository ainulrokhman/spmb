<?php

namespace App\Contracts;

use App\Models\Major;

interface MajorServiceInterface
{
    /**
     * Store a newly created major.
     */
    public function createMajor(array $data): Major;

    /**
     * Update the specified major.
     */
    public function updateMajor(Major $major, array $data): Major;

    /**
     * Delete the specified major.
     */
    public function deleteMajor(Major $major): bool;
}
