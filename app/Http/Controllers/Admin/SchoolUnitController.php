<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SchoolUnitServiceInterface;
use App\DataTables\SchoolUnitDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Admin\Concerns\ScopesUnits;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolUnit\StoreSchoolUnitRequest;
use App\Http\Requests\SchoolUnit\UpdateSchoolUnitRequest;
use App\Models\AcademicYear;
use App\Models\Program;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SchoolUnitController extends Controller
{
    use HandlesServiceExceptions, ScopesUnits;

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
            $this->injectUnitScope($request);

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

        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('admin.school-units.create', compact('programs'));
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

        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $academicYears = AcademicYear::orderByDesc('name')->get();
        $unitQuotas = $schoolUnit->unitQuotas()->pluck('quota', 'academic_year_id');

        return view('admin.school-units.edit', compact('schoolUnit', 'programs', 'academicYears', 'unitQuotas'));
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

        $this->syncUnitQuotas($schoolUnit, $request->validated('unit_quotas') ?? []);

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Unit sekolah "'.$schoolUnit->name.'" berhasil diperbarui.');
    }

    /**
     * Save the per-academic-year intake caps from the edit
     * form: an emptied field removes that year's cap (back to
     * unlimited), a filled one creates or updates it.
     *
     * @param  array<int, int|null>  $quotas  academic year id => quota
     */
    private function syncUnitQuotas(SchoolUnit $schoolUnit, array $quotas): void
    {
        foreach ($quotas as $yearId => $quota) {
            if ($quota === null) {
                $schoolUnit->unitQuotas()->where('academic_year_id', (int) $yearId)->delete();
            } else {
                $schoolUnit->unitQuotas()->updateOrCreate(
                    ['academic_year_id' => (int) $yearId],
                    ['quota' => (int) $quota],
                );
            }
        }
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
