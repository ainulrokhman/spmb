<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Renders registration numbers from the admin-configurable
 * pattern (Pengaturan key "registration_number_pattern"), so
 * each foundation can match the number format it already
 * uses instead of one fixed layout.
 *
 * Tokens: {unit} the school unit's code, {tahun} the 4-digit
 * academic year, {tahun2} its 2-digit form, {urut} the
 * sequence number, {urut:N} the sequence zero-padded to N
 * digits (1-6). Everything else in the pattern is literal
 * text. The default pattern reproduces the original fixed
 * format: unit code + 2-digit year + 3-digit sequence.
 *
 * The sequence itself is still counted by ApplicationService;
 * sequenceScope() tells it how wide to count so the rendered
 * numbers cannot collide on the unique column: a pattern
 * that names the unit counts per unit and year, a pattern
 * without the unit counts across the whole year, and a
 * pattern with neither counts globally.
 */
class RegistrationNumberFormatter
{
    public const SETTING_KEY = 'registration_number_pattern';

    public const DEFAULT_PATTERN = '{unit}{tahun2}{urut:3}';

    private const TOKENS = ['unit', 'tahun', 'tahun2', 'urut'];

    /**
     * The configured pattern, or the default when the setting
     * was never saved.
     */
    public static function pattern(): string
    {
        $pattern = trim((string) Setting::get(self::SETTING_KEY, ''));

        return $pattern !== '' ? $pattern : self::DEFAULT_PATTERN;
    }

    /**
     * Validate a pattern before it is saved. Returns a
     * Bahasa Indonesia error message, or null when valid.
     */
    public static function validatePattern(string $pattern): ?string
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return 'Pola nomor registrasi tidak boleh kosong.';
        }

        if (strlen($pattern) > 60) {
            return 'Pola nomor registrasi maksimal 60 karakter.';
        }

        $stripped = preg_replace('/\{[^{}]*\}/', '', $pattern);
        if (str_contains((string) $stripped, '{') || str_contains((string) $stripped, '}')) {
            return 'Kurung kurawal pada pola tidak berpasangan.';
        }

        preg_match_all('/\{([^{}]*)\}/', $pattern, $matches);

        $hasSequence = false;
        foreach ($matches[1] as $inner) {
            [$name, $argument] = array_pad(explode(':', $inner, 2), 2, null);

            if (! in_array($name, self::TOKENS, true)) {
                return "Token {{$inner}} tidak dikenal. Token yang tersedia: {unit}, {tahun}, {tahun2}, {urut}, {urut:N}.";
            }

            if ($name === 'urut') {
                $hasSequence = true;
                if ($argument !== null && (! ctype_digit($argument) || (int) $argument < 1 || (int) $argument > 6)) {
                    return 'Panjang {urut:N} harus antara 1 sampai 6 digit.';
                }
            } elseif ($argument !== null) {
                return "Token {{$inner}} tidak memakai panjang; tulis {{$name}} saja.";
            }
        }

        if (! $hasSequence) {
            return 'Pola wajib memuat {urut} agar nomor setiap pendaftar berbeda.';
        }

        return null;
    }

    public static function usesUnit(string $pattern): bool
    {
        return str_contains($pattern, '{unit}');
    }

    public static function usesYear(string $pattern): bool
    {
        return str_contains($pattern, '{tahun}') || str_contains($pattern, '{tahun2}');
    }

    /**
     * How wide the sequence must be counted for this pattern:
     * 'unit_year', 'year', or 'global'.
     */
    public static function sequenceScope(string $pattern): string
    {
        if (self::usesUnit($pattern)) {
            return 'unit_year';
        }

        return self::usesYear($pattern) ? 'year' : 'global';
    }

    /**
     * Render one number. Unknown tokens are left untouched,
     * so a pattern stored outside the settings screen can
     * never crash issuance.
     */
    public static function render(string $pattern, string $unitCode, string $academicYearName, int $sequence): string
    {
        $year = substr($academicYearName, 0, 4);
        $year2 = substr($academicYearName, 2, 2);

        return (string) preg_replace_callback(
            '/\{(unit|tahun2|tahun|urut)(?::(\d+))?\}/',
            fn (array $m) => match ($m[1]) {
                'unit' => $unitCode,
                'tahun' => $year,
                'tahun2' => $year2,
                'urut' => isset($m[2])
                    ? str_pad((string) $sequence, (int) $m[2], '0', STR_PAD_LEFT)
                    : (string) $sequence,
            },
            $pattern,
        );
    }
}
