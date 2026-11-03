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
     * Overview of fee settings per unit ("Biaya per Unit" menu):
     * how many components are active per unit and how many of
     * them still miss their amounts.
     */
    public function index()
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);

        $units = SchoolUnit::query()
            ->with('program')
            ->when(! auth()->user()->hasGlobalUnitAccess(), fn ($query) => $query->whereIn('id', auth()->user()->accessibleUnitIds()))
            ->orderBy('name')
            ->get()
            ->map(function (SchoolUnit $unit) {
                $mappings = $unit->fees()->withCount('amounts')->get();

                return [
                    'unit' => $unit,
                    'active' => $mappings->count(),
                    'warnings' => $mappings->filter(fn ($fee) => in_array($fee->mode, ['fixed', 'pilihan'], true) && $fee->amounts_count === 0)->count(),
                ];
            });

        return view('admin.school-units.fees-overview', compact('units'));
    }

    /**
     * Show the fee mapping matrix for a school unit.
     */
    public function edit(SchoolUnit $schoolUnit)
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);
        abort_unless(auth()->user()->hasUnitAccess($schoolUnit->id), 403);

        $components = FeeComponent::where('is_active', true)->orderBy('name')->get();
        $mappings = $schoolUnit->fees()
            ->with(['amounts' => fn ($query) => $query->orderBy('sort_order'), 'amounts.admissionTrack'])
            ->withCount('amounts')
            ->get()
            ->keyBy('fee_component_id');
        $tracks = $schoolUnit->admissionTracks()->where('is_active', true)->orderBy('name')->get();

        return view('admin.school-units.fees', compact('schoolUnit', 'components', 'mappings', 'tracks'));
    }

    /**
     * Sync the fee mappings of a school unit.
     */
    public function update(SyncUnitFeesRequest $request, SchoolUnit $schoolUnit)
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);
        abort_unless(auth()->user()->hasUnitAccess($schoolUnit->id), 403);

        $this->feeService->syncUnitFees($schoolUnit, $request->mappings());

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Pemetaan biaya untuk "'.$schoolUnit->name.'" berhasil diperbarui.');
    }
}
