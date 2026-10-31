<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FeeAmountServiceInterface;
use App\DataTables\FeeAmountDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeeAmount\StoreFeeAmountRequest;
use App\Http\Requests\FeeAmount\UpdateFeeAmountRequest;
use App\Models\AdmissionTrack;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeeAmountController extends Controller
{
    use HandlesServiceExceptions;

    protected FeeAmountServiceInterface $feeAmountService;

    /**
     * FeeAmountController constructor.
     */
    public function __construct(FeeAmountServiceInterface $feeAmountService)
    {
        $this->feeAmountService = $feeAmountService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, FeeAmountDataTable $feeAmountDataTable)
    {
        Gate::authorize('fee-amount-list');

        if ($request->ajax()) {
            return $feeAmountDataTable->make();
        }

        $units = SchoolUnit::orderBy('name')->get();
        $components = FeeComponent::orderBy('name')->get();

        return view('admin.fee-amounts.index', compact('units', 'components'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('fee-amount-create');

        return view('admin.fee-amounts.create', $this->formData());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFeeAmountRequest $request)
    {
        Gate::authorize('fee-amount-create');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->feeAmountService->createFeeAmount($request->validated()), 'admin.fee-amounts.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.fee-amounts.index')
            ->with('success', 'Nominal biaya berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FeeAmount $feeAmount)
    {
        Gate::authorize('fee-amount-edit');

        return view('admin.fee-amounts.edit', array_merge(
            $this->formData(),
            compact('feeAmount')
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFeeAmountRequest $request, FeeAmount $feeAmount)
    {
        Gate::authorize('fee-amount-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->feeAmountService->updateFeeAmount($feeAmount, $request->validated()), 'admin.fee-amounts.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.fee-amounts.index')
            ->with('success', 'Nominal biaya berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeeAmount $feeAmount)
    {
        Gate::authorize('fee-amount-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->feeAmountService->deleteFeeAmount($feeAmount), 'admin.fee-amounts.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.fee-amounts.index')
            ->with('success', 'Nominal biaya berhasil dihapus.');
    }

    /**
     * Fee mappings and tracks a fee amount form can reference.
     */
    private function formData(): array
    {
        $fees = Fee::with(['schoolUnit', 'feeComponent'])
            ->get()
            ->sortBy(fn (Fee $fee) => ($fee->schoolUnit?->name ?? '').' '.($fee->feeComponent?->name ?? ''))
            ->values();
        $tracks = AdmissionTrack::with('schoolUnit')->orderBy('name')->get();

        return compact('fees', 'tracks');
    }
}
