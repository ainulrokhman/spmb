<?php

namespace App\Services;

use App\Models\Application;
use App\Models\UnitQuota;
use InvalidArgumentException;

/**
 * Tiered intake quotas beyond the per-wave cap enforced in
 * ApplicationService::submit(): a whole school unit per
 * academic year, a major per academic year, and a major's
 * per-gender caps. Counts follow the wave quota's rule — only
 * applications already holding a registration number occupy a
 * seat — so two intakes in different academic years (this
 * year's transfers, next year's regulars) never eat each
 * other's quota.
 */
class ApplicationQuotaService
{
    /** @throws InvalidArgumentException when any tier is full. */
    public function assertQuotas(Application $application): void
    {
        $this->assertUnitQuota($application);
        $this->assertMajorQuota($application);
    }

    private function assertUnitQuota(Application $application): void
    {
        $unitQuota = UnitQuota::query()
            ->where('school_unit_id', $application->school_unit_id)
            ->where('academic_year_id', $application->academic_year_id)
            ->first();

        if ($unitQuota === null) {
            return;
        }

        $count = Application::query()
            ->where('school_unit_id', $application->school_unit_id)
            ->where('academic_year_id', $application->academic_year_id)
            ->whereNotNull('registration_number')
            ->count();

        if ($count >= $unitQuota->quota) {
            throw new InvalidArgumentException(
                'Kuota unit '.($application->schoolUnit?->name ?? '')
                .' untuk tahun ajaran '.($application->academicYear?->name ?? '')
                .' sudah penuh.'
            );
        }
    }

    private function assertMajorQuota(Application $application): void
    {
        $major = $application->major;

        if ($major === null) {
            return;
        }

        $base = Application::query()
            ->where('major_id', $major->id)
            ->where('academic_year_id', $application->academic_year_id)
            ->whereNotNull('registration_number');

        if ($major->quota !== null && (clone $base)->count() >= $major->quota) {
            throw new InvalidArgumentException('Kuota program '.$major->name.' sudah penuh.');
        }

        $genderQuota = match ($application->gender) {
            'L' => $major->quota_putra,
            'P' => $major->quota_putri,
            default => null,
        };

        if ($genderQuota !== null
            && (clone $base)->where('gender', $application->gender)->count() >= $genderQuota) {
            $label = $application->gender === 'L' ? 'putra' : 'putri';

            throw new InvalidArgumentException('Kuota program '.$major->name.' untuk '.$label.' sudah penuh.');
        }
    }
}
