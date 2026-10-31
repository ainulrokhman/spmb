<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\AcademicYearServiceInterface;
use App\DataTables\AcademicYearDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYear\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYear\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AcademicYearController extends Controller
{
    use HandlesServiceExceptions;

    protected AcademicYearServiceInterface $academicYearService;

    /**
     * AcademicYearController constructor.
     */
    public function __construct(AcademicYearServiceInterface $academicYearService)
    {
        $this->academicYearService = $academicYearService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AcademicYearDataTable $academicYearDataTable)
    {
        Gate::authorize('academic-year-list');

        if ($request->ajax()) {
            return $academicYearDataTable->make();
        }

        return view('admin.academic-years.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('academic-year-create');

        return view('admin.academic-years.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAcademicYearRequest $request)
    {
        Gate::authorize('academic-year-create');

        $academicYear = $this->academicYearService->createAcademicYear($request->validated());

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Academic year "'.$academicYear->name.'" created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AcademicYear $academicYear)
    {
        Gate::authorize('academic-year-edit');

        return view('admin.academic-years.edit', compact('academicYear'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear)
    {
        Gate::authorize('academic-year-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->academicYearService->updateAcademicYear($academicYear, $request->validated()), 'admin.academic-years.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Academic year "'.$academicYear->name.'" updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicYear $academicYear)
    {
        Gate::authorize('academic-year-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->academicYearService->deleteAcademicYear($academicYear), 'admin.academic-years.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Academic year deleted successfully.');
    }
}
