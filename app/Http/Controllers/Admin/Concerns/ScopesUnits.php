<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Unit scoping helpers for master-data controllers: committee
 * members (any non-global user) only see and touch the school
 * units they are assigned to; global roles are unaffected.
 */
trait ScopesUnits
{
    /**
     * Units usable in filter dropdowns and forms for the current
     * user.
     */
    protected function accessibleUnits(): Collection
    {
        $user = auth()->user();

        return SchoolUnit::query()
            ->when($user !== null && ! $user->hasGlobalUnitAccess(),
                fn ($query) => $query->whereIn('id', $user->accessibleUnitIds()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Inject the unit_scope value DataTables filter on when the
     * current user is limited to assigned units.
     */
    protected function injectUnitScope(Request $request): void
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasGlobalUnitAccess()) {
            $request->merge(['unit_scope' => implode(',', $user->accessibleUnitIds())]);
        }
    }

    /**
     * Guard a single record's unit against the current user's
     * assignments.
     */
    protected function ensureUnitAccess(int $schoolUnitId): void
    {
        abort_unless(auth()->user()?->hasUnitAccess($schoolUnitId), 403);
    }
}
