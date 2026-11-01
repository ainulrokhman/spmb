<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A school unit id passes when the current user may work with that
 * unit: global roles always may, everyone else only through their
 * unit assignments (Fase 1E/6).
 */
class UnitAccessible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();

        if ($user === null || $user->hasGlobalUnitAccess()) {
            return;
        }

        if (! $user->hasUnitAccess((int) $value)) {
            $fail('Unit sekolah berada di luar penugasan Anda.');
        }
    }
}
