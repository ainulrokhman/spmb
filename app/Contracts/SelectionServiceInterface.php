<?php

namespace App\Contracts;

use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SelectionRule;
use App\Models\User;
use Illuminate\Support\Collection;

interface SelectionServiceInterface
{
    /**
     * (Re)compute the automatic part of an application's
     * selection score from its unit's active rules, preserving
     * any manual entries and the committee adjustment. Returns
     * the stored snapshot, or null when the unit does not use
     * selection points.
     */
    public function calculate(Application $application): ?array;

    /**
     * Enter (or change) the points for one manual criterion.
     */
    public function setManualScore(Application $application, SelectionRule $rule, int $points, User $recorder): array;

    /**
     * Set the committee's manual adjustment (+/−) on top of
     * the computed total; a non-zero adjustment needs a reason.
     */
    public function setAdjustment(Application $application, int $value, ?string $reason, User $recorder): array;

    /**
     * Verified applications of a wave ranked by total score,
     * each paired with the decision the wave's passing score
     * would give it.
     *
     * @return Collection<int, array{application: Application, total: int, decision: string}>
     */
    public function previewByPoints(AdmissionWave $wave): Collection;

    /**
     * Apply the previewed decisions to every verified
     * application of the wave.
     *
     * @return array{passed: int, failed: int}
     */
    public function applyByPoints(AdmissionWave $wave): array;
}
