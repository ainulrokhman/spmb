<?php

namespace App\Support;

/**
 * Normalizes Indonesian mobile numbers to the local 08… form so a
 * guardian's phone identity is stored and compared consistently,
 * however it was typed (08…, +62…, or 62…).
 */
final class PhoneNumber
{
    /**
     * Normalize a raw phone input to the 08… form, or null when the
     * input cannot be an Indonesian mobile number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        return self::isValid($digits) ? $digits : null;
    }

    /**
     * Whether the value is a plausible Indonesian mobile number in
     * normalized 08… form.
     */
    public static function isValid(?string $value): bool
    {
        return $value !== null && preg_match('/^08\d{8,12}$/', $value) === 1;
    }
}
