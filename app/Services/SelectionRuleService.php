<?php

namespace App\Services;

use App\Contracts\SelectionRuleServiceInterface;
use App\Models\ChildStatus;
use App\Models\SchoolUnit;
use App\Models\SelectionRule;
use InvalidArgumentException;

class SelectionRuleService implements SelectionRuleServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function createRule(SchoolUnit $unit, array $data): SelectionRule
    {
        return SelectionRule::create([
            'school_unit_id' => $unit->id,
            'kind' => $data['kind'],
            'name' => $data['name'],
            'config' => $this->buildConfig($unit, $data),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => ! empty($data['is_active']),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function updateRule(SelectionRule $rule, array $data): SelectionRule
    {
        $rule->update([
            'kind' => $data['kind'],
            'name' => $data['name'],
            'config' => $this->buildConfig($rule->schoolUnit, $data),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => ! empty($data['is_active']),
        ]);

        return $rule;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteRule(SelectionRule $rule): bool
    {
        return (bool) $rule->delete();
    }

    /**
     * Build the kind-specific config from raw form inputs.
     * Line-based settings use one entry per line:
     * brackets/ranges are "min|max|poin" (max may be empty
     * for "and up"), the choice map is "jawaban|poin".
     */
    private function buildConfig(SchoolUnit $unit, array $data): array
    {
        return match ($data['kind']) {
            SelectionRule::KIND_AGE => [
                'brackets' => $this->parseRanges($data['brackets_text'] ?? '', 'rentang umur'),
            ],
            SelectionRule::KIND_NUMERIC => [
                'field_key' => $this->requireField($unit, $data, ['number']),
                'ranges' => $this->parseRanges($data['ranges_text'] ?? '', 'rentang nilai'),
            ],
            SelectionRule::KIND_CHOICE => [
                'field_key' => $this->requireField($unit, $data, ['select', 'radio', 'checkbox']),
                'map' => $this->parseMap($data['map_text'] ?? ''),
            ],
            SelectionRule::KIND_ORIGIN => [
                'field_key' => $this->requireField($unit, $data, ['school_unit']),
                'points' => $this->integer($data['points'] ?? null, 'Poin asal sekolah'),
            ],
            SelectionRule::KIND_SIBLINGS => [
                'field_key' => $this->requireField($unit, $data, ['siblings']),
                'points_internal' => $this->integer($data['points_internal'] ?? null, 'Poin per saudara di yayasan'),
                'points_external' => $this->integer($data['points_external'] ?? null, 'Poin per saudara di luar yayasan'),
                'cap' => blank($data['cap'] ?? null) ? null : $this->integer($data['cap'], 'Batas maksimum poin saudara'),
            ],
            SelectionRule::KIND_CHILD_STATUS => [
                'map' => $this->parseStatusMap($data['status_points'] ?? []),
            ],
            SelectionRule::KIND_MANUAL => [
                'max_points' => $this->integer($data['max_points'] ?? null, 'Poin maksimum kriteria'),
            ],
            default => throw new InvalidArgumentException('Jenis aturan tidak dikenal.'),
        };
    }

    /**
     * The chosen form field must exist on the unit and be
     * one of the types the rule kind can read.
     *
     * @param  list<string>  $types
     */
    private function requireField(SchoolUnit $unit, array $data, array $types): string
    {
        $key = (string) ($data['field_key'] ?? '');
        $exists = $unit->formFields()
            ->where('key', $key)
            ->whereIn('type', $types)
            ->exists();

        if ($key === '' || ! $exists) {
            throw new InvalidArgumentException('Bidang formulir sumber wajib dipilih dari bidang unit ini yang sesuai jenisnya.');
        }

        return $key;
    }

    /**
     * Parse "min|max|poin" lines into bracket/range entries.
     *
     * @return list<array{min: float, max: float|null, points: int}>
     */
    private function parseRanges(string $text, string $label): array
    {
        $entries = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if (count($parts) !== 3 || ! is_numeric($parts[0]) || ($parts[1] !== '' && ! is_numeric($parts[1])) || ! is_numeric($parts[2])) {
                throw new InvalidArgumentException('Baris '.$label.' tidak valid: "'.$line.'". Gunakan format min|max|poin (max boleh kosong).');
            }

            $entries[] = [
                'min' => (float) $parts[0],
                'max' => $parts[1] === '' ? null : (float) $parts[1],
                'points' => (int) $parts[2],
            ];
        }

        if ($entries === []) {
            throw new InvalidArgumentException('Minimal satu baris '.$label.' wajib diisi.');
        }

        return $entries;
    }

    /**
     * Parse "jawaban|poin" lines into an answer→points map.
     *
     * @return array<string, int>
     */
    private function parseMap(string $text): array
    {
        $map = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if (count($parts) !== 2 || $parts[0] === '' || ! is_numeric($parts[1])) {
                throw new InvalidArgumentException('Baris pemetaan jawaban tidak valid: "'.$line.'". Gunakan format jawaban|poin.');
            }

            $map[$parts[0]] = (int) $parts[1];
        }

        if ($map === []) {
            throw new InvalidArgumentException('Minimal satu baris pemetaan jawaban wajib diisi.');
        }

        return $map;
    }

    /**
     * Status points arrive as status_id => points inputs;
     * blank and zero entries are dropped from the map.
     *
     * @return array<int, int>
     */
    private function parseStatusMap(array $inputs): array
    {
        $validIds = ChildStatus::pluck('id')->all();
        $map = [];

        foreach ($inputs as $statusId => $points) {
            if (blank($points)) {
                continue;
            }

            if (! in_array((int) $statusId, $validIds, true) || ! is_numeric($points)) {
                throw new InvalidArgumentException('Poin status anak tidak valid.');
            }

            if ((int) $points !== 0) {
                $map[(int) $statusId] = (int) $points;
            }
        }

        if ($map === []) {
            throw new InvalidArgumentException('Minimal satu status anak diberi poin.');
        }

        return $map;
    }

    /**
     * Validate an integer form input (negatives allowed for
     * adjustments of policy, points themselves are checked
     * by the caller's semantics).
     */
    private function integer(mixed $value, string $label): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            throw new InvalidArgumentException($label.' wajib berupa angka.');
        }

        return (int) $value;
    }
}
