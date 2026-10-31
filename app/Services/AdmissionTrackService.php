<?php

namespace App\Services;

use App\Contracts\AdmissionTrackServiceInterface;
use App\Models\AdmissionTrack;

class AdmissionTrackService implements AdmissionTrackServiceInterface
{
    /**
     * Store a newly created admission track.
     */
    public function createAdmissionTrack(array $data): AdmissionTrack
    {
        return AdmissionTrack::create($this->trackAttributes($data));
    }

    /**
     * Update the specified admission track.
     */
    public function updateAdmissionTrack(AdmissionTrack $admissionTrack, array $data): AdmissionTrack
    {
        $admissionTrack->update($this->trackAttributes($data));

        return $admissionTrack;
    }

    /**
     * Delete the specified admission track.
     */
    public function deleteAdmissionTrack(AdmissionTrack $admissionTrack): bool
    {
        return (bool) $admissionTrack->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function trackAttributes(array $data): array
    {
        return [
            'school_unit_id' => $data['school_unit_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
