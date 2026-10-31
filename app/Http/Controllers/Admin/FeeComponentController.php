<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FeeComponentServiceInterface;
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

    /**
     * FeeComponentController constructor.
     */
    public function __construct(FeeComponentServiceInterface $feeComponentService)
    {
        $this->feeComponentService = $feeComponentService;
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
