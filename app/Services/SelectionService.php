<?php

namespace App\Services;

use App\Contracts\ApplicationServiceInterface;
use App\Contracts\SelectionServiceInterface;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SelectionRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SelectionService implements SelectionServiceInterface
{
    public function __construct(
        protected ApplicationServiceInterface $applications
    ) {}

    /**
     * {@inheritDoc}
     */
    public function calculate(Application $application): ?array
    {
        $unit = $application->schoolUnit;

        if ($unit === null || ! $unit->selection_enabled) {
            return null;
        }

        $previous = $application->selection_scores ?? [];
        $auto = [];
        $autoTotal = 0;

        $rules = $unit->selectionRules()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->kind === SelectionRule::KIND_MANUAL) {
                continue;
            }

            [$points, $detail] = $this->evaluate($rule, $application);
            $auto[] = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'kind' => $rule->kind,
                'points' => $points,
                'detail' => $detail,
            ];
            $autoTotal += $points;
        }

        // Manual entries and the adjustment survive every
        // recalculation untouched — only entries whose rule was
        // deleted fall away.
        $manualRuleIds = $rules->where('kind', SelectionRule::KIND_MANUAL)->pluck('id')->all();
        $manual = collect($previous['manual'] ?? [])
            ->filter(fn ($entry, $ruleId) => in_array((int) $ruleId, $manualRuleIds, true))
            ->all();
        $manualTotal = collect($manual)->sum('points');
        $adjustment = $previous['adjustment'] ?? null;
        $adjustmentValue = (int) ($adjustment['value'] ?? 0);

        $snapshot = [
            'auto' => $auto,
            'auto_total' => $autoTotal,
            'manual' => $manual,
            'manual_total' => $manualTotal,
            'adjustment' => $adjustment,
            'total' => $autoTotal + $manualTotal + $adjustmentValue,
            'calculated_at' => now()->toDateTimeString(),
        ];

        $application->update([
            'selection_scores' => $snapshot,
            'selection_total' => $snapshot['total'],
        ]);

        return $snapshot;
    }

    /**
     * {@inheritDoc}
     */
    public function setManualScore(Application $application, SelectionRule $rule, int $points, User $recorder): array
    {
        $this->ensureScoringOpen($application);

        if ($rule->kind !== SelectionRule::KIND_MANUAL || $rule->school_unit_id !== $application->school_unit_id) {
            throw new InvalidArgumentException('Kriteria manual tidak ditemukan untuk unit pendaftar ini.');
        }

        if ($points < 0 || $points > $rule->maxPoints()) {
            throw new InvalidArgumentException('Poin kriteria "'.$rule->name.'" harus antara 0 dan '.$rule->maxPoints().'.');
        }

        $snapshot = $this->calculate($application) ?? throw new InvalidArgumentException('Unit pendaftar ini tidak memakai seleksi poin.');

        $manual = $snapshot['manual'] ?? [];
        $manual[$rule->id] = [
            'points' => $points,
            'by' => $recorder->id,
            'by_name' => $recorder->name,
            'at' => now()->toDateTimeString(),
        ];

        return $this->storeManualAndAdjustment($application, $snapshot, $manual, $snapshot['adjustment'] ?? null);
    }

    /**
     * {@inheritDoc}
     */
    public function setAdjustment(Application $application, int $value, ?string $reason, User $recorder): array
    {
        $this->ensureScoringOpen($application);

        if ($value !== 0 && blank($reason)) {
            throw new InvalidArgumentException('Alasan wajib diisi untuk koreksi poin.');
        }

        $snapshot = $this->calculate($application) ?? throw new InvalidArgumentException('Unit pendaftar ini tidak memakai seleksi poin.');

        $adjustment = $value === 0 ? null : [
            'value' => $value,
            'reason' => $reason,
            'by' => $recorder->id,
            'by_name' => $recorder->name,
            'at' => now()->toDateTimeString(),
        ];

        return $this->storeManualAndAdjustment($application, $snapshot, $snapshot['manual'] ?? [], $adjustment);
    }

    /**
     * {@inheritDoc}
     */
    public function previewByPoints(AdmissionWave $wave): Collection
    {
        if ($wave->passing_score === null) {
            throw new InvalidArgumentException('Gelombang ini belum memiliki batas poin lulus.');
        }

        return $wave->applications()
            ->where('status', 'verified')
            ->get()
            ->map(function (Application $application) use ($wave) {
                if ($application->selection_total === null) {
                    $this->calculate($application);
                    $application->refresh();
                }

                $total = (int) ($application->selection_total ?? 0);

                return [
                    'application' => $application,
                    'total' => $total,
                    'decision' => $total >= $wave->passing_score ? 'passed' : 'failed',
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * {@inheritDoc}
     */
    public function applyByPoints(AdmissionWave $wave): array
    {
        $counts = ['passed' => 0, 'failed' => 0];

        foreach ($this->previewByPoints($wave) as $row) {
            $this->applications->decideAnnouncement($row['application'], $row['decision']);
            $counts[$row['decision']]++;
        }

        return $counts;
    }

    /**
     * Persist manual entries and the adjustment on top of a
     * freshly calculated snapshot and refresh the total.
     */
    private function storeManualAndAdjustment(Application $application, array $snapshot, array $manual, ?array $adjustment): array
    {
        $manualTotal = collect($manual)->sum('points');
        $snapshot['manual'] = $manual;
        $snapshot['manual_total'] = $manualTotal;
        $snapshot['adjustment'] = $adjustment;
        $snapshot['total'] = ($snapshot['auto_total'] ?? 0) + $manualTotal + (int) ($adjustment['value'] ?? 0);

        $application->update([
            'selection_scores' => $snapshot,
            'selection_total' => $snapshot['total'],
        ]);

        return $snapshot;
    }

    /**
     * Manual entries and the adjustment lock once the result
     * (passed/failed) has been decided.
     */
    private function ensureScoringOpen(Application $application): void
    {
        if (in_array($application->status, ['passed', 'failed', 're_registered'], true)) {
            throw new InvalidArgumentException('Poin sudah terkunci karena hasil pendaftaran telah ditetapkan.');
        }
    }

    /**
     * Evaluate one automatic rule against the application.
     *
     * @return array{0: int, 1: string}
     */
    private function evaluate(SelectionRule $rule, Application $application): array
    {
        return match ($rule->kind) {
            SelectionRule::KIND_AGE => $this->evaluateAge($rule, $application),
            SelectionRule::KIND_CHOICE => $this->evaluateChoice($rule, $application),
            SelectionRule::KIND_NUMERIC => $this->evaluateNumeric($rule, $application),
            SelectionRule::KIND_ORIGIN => $this->evaluateOrigin($rule, $application),
            SelectionRule::KIND_SIBLINGS => $this->evaluateSiblings($rule, $application),
            SelectionRule::KIND_CHILD_STATUS => $this->evaluateChildStatus($rule, $application),
            default => [0, ''],
        };
    }

    /**
     * Age brackets against the reference date: 1 July of the
     * first year of the application's academic year (the
     * start of the new school year).
     */
    private function evaluateAge(SelectionRule $rule, Application $application): array
    {
        if ($application->birth_date === null) {
            return [0, 'Tanggal lahir belum terisi'];
        }

        $yearName = $application->academicYear?->name ?? '';
        $firstYear = (int) substr($yearName, 0, 4);

        if ($firstYear < 1900) {
            return [0, 'Tahun ajaran tidak dikenal'];
        }

        $reference = Carbon::create($firstYear, 7, 1);
        $age = $application->birth_date->isAfter($reference)
            ? 0
            : (int) $application->birth_date->diffInYears($reference);

        foreach ($rule->config['brackets'] ?? [] as $bracket) {
            $min = (int) ($bracket['min'] ?? 0);
            $max = $bracket['max'] ?? null;

            if ($age >= $min && ($max === null || $age <= (int) $max)) {
                return [(int) ($bracket['points'] ?? 0), 'Umur '.$age.' tahun per 1 Juli '.$firstYear];
            }
        }

        return [0, 'Umur '.$age.' tahun per 1 Juli '.$firstYear.' (di luar rentang)'];
    }

    /**
     * Points per answer of a choice field (for checkbox
     * answers the points of every chosen option add up).
     */
    private function evaluateChoice(SelectionRule $rule, Application $application): array
    {
        $key = $rule->config['field_key'] ?? '';
        $map = $rule->config['map'] ?? [];
        $answer = $application->answers[$key] ?? null;

        if ($answer === null || $answer === '') {
            return [0, 'Belum dijawab'];
        }

        $answers = is_array($answer) ? $answer : [$answer];
        $points = collect($answers)->sum(fn ($one) => (int) ($map[$one] ?? 0));

        return [$points, 'Jawaban: '.implode(', ', $answers)];
    }

    /**
     * Points for a numeric answer inside a configured range.
     */
    private function evaluateNumeric(SelectionRule $rule, Application $application): array
    {
        $key = $rule->config['field_key'] ?? '';
        $answer = $application->answers[$key] ?? null;

        if ($answer === null || $answer === '' || ! is_numeric($answer)) {
            return [0, 'Belum dijawab'];
        }

        $value = (float) $answer;

        foreach ($rule->config['ranges'] ?? [] as $range) {
            $min = (float) ($range['min'] ?? 0);
            $max = $range['max'] ?? null;

            if ($value >= $min && ($max === null || $value <= (float) $max)) {
                return [(int) ($range['points'] ?? 0), 'Nilai: '.rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',')];
            }
        }

        return [0, 'Nilai: '.$answer.' (di luar rentang)'];
    }

    /**
     * Points when the origin-school answer is one of the
     * foundation's own units (stored as the unit id).
     */
    private function evaluateOrigin(SelectionRule $rule, Application $application): array
    {
        $key = $rule->config['field_key'] ?? '';
        $answer = $application->answers[$key] ?? null;

        if ($answer === null || $answer === '') {
            return [0, 'Belum dijawab'];
        }

        if (! is_numeric($answer)) {
            return [0, 'Sekolah di luar yayasan'];
        }

        $unit = \App\Models\SchoolUnit::find((int) $answer);

        if ($unit === null) {
            return [0, 'Sekolah di luar yayasan'];
        }

        return [(int) ($rule->config['points'] ?? 0), 'Lulusan '.$unit->name];
    }

    /**
     * Points per declared sibling: one value for siblings at
     * a foundation unit, another for outside schools, with an
     * optional cap. Each foundation sibling is cross-checked
     * against re-registered applications at that unit — the
     * outcome is a verification hint in the detail text, not
     * a gate on the points.
     */
    private function evaluateSiblings(SelectionRule $rule, Application $application): array
    {
        $key = $rule->config['field_key'] ?? '';
        $rows = $application->answers[$key] ?? [];

        if (! is_array($rows) || $rows === []) {
            return [0, 'Tidak ada saudara dinyatakan'];
        }

        $pointsInternal = (int) ($rule->config['points_internal'] ?? 0);
        $pointsExternal = (int) ($rule->config['points_external'] ?? 0);
        $cap = $rule->config['cap'] ?? null;

        $total = 0;
        $details = [];

        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');

            if (! empty($row['unit_id'])) {
                $total += $pointsInternal;
                $unit = \App\Models\SchoolUnit::find((int) $row['unit_id']);
                $found = $unit !== null && Application::query()
                    ->where('school_unit_id', $unit->id)
                    ->where('status', 're_registered')
                    ->whereRaw('LOWER(child_name) = ?', [mb_strtolower(trim($name))])
                    ->whereKeyNot($application->id)
                    ->exists();
                $details[] = $name.' ('.($unit?->name ?? 'unit').', '.($found ? 'cocok dengan siswa daftar ulang' : 'belum ditemukan sebagai siswa daftar ulang').')';
            } else {
                $total += $pointsExternal;
                $details[] = $name.' ('.($row['school_name'] ?? 'sekolah luar').')';
            }
        }

        if ($cap !== null) {
            $total = min($total, (int) $cap);
        }

        return [$total, implode('; ', $details)];
    }

    /**
     * Points mapped from the application's verified child
     * status (the stored status, or Reguler when unset).
     */
    private function evaluateChildStatus(SelectionRule $rule, Application $application): array
    {
        $statusId = $application->effectiveChildStatusId();
        $map = $rule->config['map'] ?? [];
        $points = (int) ($map[$statusId] ?? $map[(string) $statusId] ?? 0);

        return [$points, 'Status: '.$application->childStatusLabel()];
    }
}
