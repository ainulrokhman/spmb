<?php

namespace App\Contracts;

use App\Models\AdmissionTrack;

interface AdmissionTrackServiceInterface
{
    /**
     * Store a newly created admission track.
     */
    public function createAdmissionTrack(array $data): AdmissionTrack;

    /**
     * Update the specified admission track.
     */
    public function updateAdmissionTrack(AdmissionTrack $admissionTrack, array $data): AdmissionTrack;

    /**
     * Delete the specified admission track.
     */
    public function deleteAdmissionTrack(AdmissionTrack $admissionTrack): bool;
}
