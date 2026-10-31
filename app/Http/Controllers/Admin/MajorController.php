<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\MajorServiceInterface;
use App\DataTables\MajorDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Major\StoreMajorRequest;
use App\Http\Requests\Major\UpdateMajorRequest;
use App\Models\Major;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MajorController extends Controller
{
    use HandlesServiceExceptions;

    protected MajorServiceInterface $majorService;

    /**
     * MajorController constructor.
     */
    public function __construct(MajorServiceInterface $majorService)
    {
        $this->majorService = $majorService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, MajorDataTable $majorDataTable)
    {
        Gate::authorize('major-list');

        if ($request->ajax()) {
            return $majorDataTable->make();
        }

        $units = SchoolUnit::orderBy('name')->get();

        return view('admin.majors.index', compact('units'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('major-create');

        $units = SchoolUnit::orderBy('name')->get();

        return view('admin.majors.create', compact('units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMajorRequest $request)
    {
        Gate::authorize('major-create');

        $major = $this->majorService->createMajor($request->validated());

        return redirect()->route('admin.majors.index')
            ->with('success', 'Jurusan "'.$major->name.'" berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Major $major)
    {
        Gate::authorize('major-edit');

        $units = SchoolUnit::orderBy('name')->get();

        return view('admin.majors.edit', compact('major', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMajorRequest $request, Major $major)
    {
        Gate::authorize('major-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->majorService->updateMajor($major, $request->validated()), 'admin.majors.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.majors.index')
            ->with('success', 'Jurusan "'.$major->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Major $major)
    {
        Gate::authorize('major-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->majorService->deleteMajor($major), 'admin.majors.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.majors.index')
            ->with('success', 'Jurusan berhasil dihapus.');
    }
}
