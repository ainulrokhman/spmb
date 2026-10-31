<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FeeServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fee\SyncUnitFeesRequest;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Support\Facades\Gate;

class UnitFeeController extends Controller
{
    protected FeeServiceInterface $feeService;

    /**
     * UnitFeeController constructor.
     */
    public function __construct(FeeServiceInterface $feeService)
    {
        $this->feeService = $feeService;
    }

    /**
     * Show the fee mapping matrix for a school unit.
     */
    public function edit(SchoolUnit $schoolUnit)
    {
        Gate::authorize('school-unit-edit');

        $components = FeeComponent::where('is_active', true)->orderBy('name')->get();
        $mappings = $schoolUnit->fees()->withCount('amounts')->get()->keyBy('fee_component_id');

        return view('admin.school-units.fees', compact('schoolUnit', 'components', 'mappings'));
    }

    /**
     * Sync the fee mappings of a school unit.
     */
    public function update(SyncUnitFeesRequest $request, SchoolUnit $schoolUnit)
    {
        Gate::authorize('school-unit-edit');

        $this->feeService->syncUnitFees($schoolUnit, $request->mappings());

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Pemetaan biaya untuk "'.$schoolUnit->name.'" berhasil diperbarui.');
    }
}
