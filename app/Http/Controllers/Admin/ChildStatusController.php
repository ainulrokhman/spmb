<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ChildStatusServiceInterface;
use App\DataTables\ChildStatusDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChildStatus\StoreChildStatusRequest;
use App\Http\Requests\ChildStatus\UpdateChildStatusRequest;
use App\Models\ChildStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChildStatusController extends Controller
{
    use HandlesServiceExceptions;

    protected ChildStatusServiceInterface $childStatusService;

    /**
     * ChildStatusController constructor.
     */
    public function __construct(ChildStatusServiceInterface $childStatusService)
    {
        $this->programService = $childStatusService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ChildStatusDataTable $childStatusDataTable)
    {
        Gate::authorize('child-status-list');

        if ($request->ajax()) {
            return $childStatusDataTable->make();
        }

        return view('admin.child-statuses.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('child-status-create');

        return view('admin.child-statuses.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChildStatusRequest $request)
    {
        Gate::authorize('child-status-create');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->programService->createChildStatus($request->validated()), 'admin.child-statuses.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.child-statuses.index')
            ->with('success', 'ChildStatus berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChildStatus $childStatus)
    {
        Gate::authorize('child-status-edit');

        return view('admin.child-statuses.edit', compact('program'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChildStatusRequest $request, ChildStatus $childStatus)
    {
        Gate::authorize('child-status-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->programService->updateChildStatus($childStatus, $request->validated()), 'admin.child-statuses.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.child-statuses.index')
            ->with('success', 'ChildStatus "'.$childStatus->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChildStatus $childStatus)
    {
        Gate::authorize('child-status-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->programService->deleteChildStatus($childStatus), 'admin.child-statuses.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.child-statuses.index')
            ->with('success', 'ChildStatus berhasil dihapus.');
    }
}
