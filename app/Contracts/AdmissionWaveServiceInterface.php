<?php

namespace App\Contracts;

use App\Models\AdmissionWave;

interface AdmissionWaveServiceInterface
{
    /**
     * Store a newly created admission wave.
     */
    public function createAdmissionWave(array $data): AdmissionWave;

    /**
     * Update the specified admission wave.
     */
    public function updateAdmissionWave(AdmissionWave $admissionWave, array $data): AdmissionWave;

    /**
     * Delete the specified admission wave.
     */
    public function deleteAdmissionWave(AdmissionWave $admissionWave): bool;
}
