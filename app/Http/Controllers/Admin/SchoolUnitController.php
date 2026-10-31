<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SchoolUnitServiceInterface;
use App\DataTables\SchoolUnitDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolUnit\StoreSchoolUnitRequest;
use App\Http\Requests\SchoolUnit\UpdateSchoolUnitRequest;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolUnitController extends Controller
{
    use HandlesServiceExceptions;

    protected SchoolUnitServiceInterface $schoolUnitService;

    /**
     * SchoolUnitController constructor.
     */
    public function __construct(SchoolUnitServiceInterface $schoolUnitService)
    {
        $this->schoolUnitService = $schoolUnitService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, SchoolUnitDataTable $schoolUnitDataTable)
    {
        Gate::authorize('school-unit-list');

        if ($request->ajax()) {
            return $schoolUnitDataTable->make();
        }

        return view('admin.school-units.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('school-unit-create');

        return view('admin.school-units.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSchoolUnitRequest $request)
    {
        Gate::authorize('school-unit-create');

        $schoolUnit = $this->schoolUnitService->createSchoolUnit($request->validated());

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Unit sekolah "'.$schoolUnit->name.'" berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolUnit $schoolUnit)
    {
        Gate::authorize('school-unit-edit');

        return view('admin.school-units.edit', compact('schoolUnit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolUnitRequest $request, SchoolUnit $schoolUnit)
    {
        Gate::authorize('school-unit-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->schoolUnitService->updateSchoolUnit($schoolUnit, $request->validated()), 'admin.school-units.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Unit sekolah "'.$schoolUnit->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolUnit $schoolUnit)
    {
        Gate::authorize('school-unit-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->schoolUnitService->deleteSchoolUnit($schoolUnit), 'admin.school-units.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Unit sekolah berhasil dihapus.');
    }
}
