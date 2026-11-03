<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FeeComponentServiceInterface;
use App\Contracts\FeeServiceInterface;
use App\Http\Requests\Fee\SyncComponentUnitsRequest;
use App\Models\SchoolUnit;
use App\DataTables\FeeComponentDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeeComponent\StoreFeeComponentRequest;
use App\Http\Requests\FeeComponent\UpdateFeeComponentRequest;
use App\Models\FeeComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeeComponentController extends Controller
{
    use HandlesServiceExceptions;

    protected FeeComponentServiceInterface $feeComponentService;

    protected FeeServiceInterface $feeService;

    /**
     * FeeComponentController constructor.
     */
    public function __construct(FeeComponentServiceInterface $feeComponentService, FeeServiceInterface $feeService)
    {
        $this->feeComponentService = $feeComponentService;
        $this->feeService = $feeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, FeeComponentDataTable $feeComponentDataTable)
    {
        Gate::authorize('fee-component-list');

        if ($request->ajax()) {
            return $feeComponentDataTable->make();
        }

        return view('admin.fee-components.index');
    }

    /**
     * Show this component's fee mode for every unit at once (the
     * "Atur per Unit" page), limited to units the user may access.
     */
    public function units(FeeComponent $feeComponent)
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);

        $units = $this->accessibleFeeUnits();
        $mappings = $feeComponent->fees()->withCount('amounts')->get()->keyBy('school_unit_id');

        return view('admin.fee-components.units', compact('feeComponent', 'units', 'mappings'));
    }

    /**
     * Save this component's fee mode across the submitted units.
     * Units outside the user's access are rejected outright.
     */
    public function updateUnits(SyncComponentUnitsRequest $request, FeeComponent $feeComponent)
    {
        $perUnit = $request->units();
        $accessibleIds = $this->accessibleFeeUnits()->pluck('id')->all();

        foreach (array_keys($perUnit) as $unitId) {
            abort_unless(in_array($unitId, $accessibleIds, true), 403);
        }

        $this->feeService->syncComponentFees($feeComponent, $perUnit);

        return redirect()->route('admin.fee-components.units', $feeComponent)
            ->with('success', 'Mode "'.$feeComponent->name.'" per unit berhasil diperbarui.');
    }

    /**
     * Units the current user may manage fees for.
     */
    private function accessibleFeeUnits()
    {
        return SchoolUnit::query()
            ->with('program')
            ->when(! auth()->user()->hasGlobalUnitAccess(), fn ($query) => $query->whereIn('id', auth()->user()->accessibleUnitIds()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('fee-component-create');

        return view('admin.fee-components.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFeeComponentRequest $request)
    {
        Gate::authorize('fee-component-create');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->feeComponentService->createFeeComponent($request->validated()), 'admin.fee-components.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.fee-components.index')
            ->with('success', 'Komponen biaya berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FeeComponent $feeComponent)
    {
        Gate::authorize('fee-component-edit');

        return view('admin.fee-components.edit', compact('feeComponent'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFeeComponentRequest $request, FeeComponent $feeComponent)
    {
        Gate::authorize('fee-component-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->feeComponentService->updateFeeComponent($feeComponent, $request->validated()), 'admin.fee-components.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.fee-components.index')
            ->with('success', 'Komponen biaya "'.$feeComponent->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeeComponent $feeComponent)
    {
        Gate::authorize('fee-component-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->feeComponentService->deleteFeeComponent($feeComponent), 'admin.fee-components.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.fee-components.index')
            ->with('success', 'Fee component berhasil dihapus.');
    }
}
