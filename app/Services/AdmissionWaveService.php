<?php

namespace App\Services;

use App\Contracts\AdmissionWaveServiceInterface;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdmissionWaveService implements AdmissionWaveServiceInterface
{
    /**
     * Store a newly created admission wave.
     */
    public function createAdmissionWave(array $data): AdmissionWave
    {
        return DB::transaction(function () use ($data) {
            $admissionWave = AdmissionWave::create($this->waveAttributes($data));

            $this->syncTracks($admissionWave, $data['tracks'] ?? []);

            return $admissionWave;
        });
    }

    /**
     * Update the specified admission wave.
     */
    public function updateAdmissionWave(AdmissionWave $admissionWave, array $data): AdmissionWave
    {
        return DB::transaction(function () use ($admissionWave, $data) {
            $admissionWave->update($this->waveAttributes($data));

            $this->syncTracks($admissionWave, $data['tracks'] ?? []);

            return $admissionWave;
        });
    }

    /**
     * Delete the specified admission wave.
     */
    public function deleteAdmissionWave(AdmissionWave $admissionWave): bool
    {
        return (bool) $admissionWave->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function waveAttributes(array $data): array
    {
        return [
            'school_unit_id' => $data['school_unit_id'],
            'academic_year_id' => $data['academic_year_id'],
            'name' => $data['name'],
            'quota' => $data['quota'] ?? null,
            'opens_at' => $data['opens_at'],
            'closes_at' => $data['closes_at'],
            'is_active' => ! empty($data['is_active']),
        ];
    }

    /**
     * Sync the tracks opened by the wave.
     *
     * A wave may only open tracks that belong to its own school unit;
     * anything else is rejected even if it passed the form request.
     *
     * @param  array<int, int>  $trackIds
     */
    private function syncTracks(AdmissionWave $admissionWave, array $trackIds): void
    {
        $trackIds = array_values(array_unique(array_map('intval', $trackIds)));

        if ($trackIds !== []) {
            $ownedCount = AdmissionTrack::where('school_unit_id', $admissionWave->school_unit_id)
                ->whereIn('id', $trackIds)
                ->count();

            if ($ownedCount !== count($trackIds)) {
                throw new InvalidArgumentException('Admission tracks must belong to the same school unit as the wave.');
            }
        }

        $admissionWave->admissionTracks()->sync($trackIds);
    }
}
