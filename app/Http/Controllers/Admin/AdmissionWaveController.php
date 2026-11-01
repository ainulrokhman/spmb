<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\AdmissionWaveServiceInterface;
use App\DataTables\AdmissionWaveDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Admin\Concerns\ScopesUnits;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdmissionWave\StoreAdmissionWaveRequest;
use App\Http\Requests\AdmissionWave\UpdateAdmissionWaveRequest;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdmissionWaveController extends Controller
{
    use HandlesServiceExceptions, ScopesUnits;

    protected AdmissionWaveServiceInterface $admissionWaveService;

    /**
     * AdmissionWaveController constructor.
     */
    public function __construct(AdmissionWaveServiceInterface $admissionWaveService)
    {
        $this->admissionWaveService = $admissionWaveService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AdmissionWaveDataTable $admissionWaveDataTable)
    {
        Gate::authorize('admission-wave-list');

        if ($request->ajax()) {
            $this->injectUnitScope($request);

            return $admissionWaveDataTable->make();
        }

        $units = $this->accessibleUnits();

        return view('admin.admission-waves.index', compact('units'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('admission-wave-create');

        return view('admin.admission-waves.create', $this->formData());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdmissionWaveRequest $request)
    {
        Gate::authorize('admission-wave-create');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->admissionWaveService->createAdmissionWave($request->validated()), 'admin.admission-waves.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.admission-waves.index')
            ->with('success', 'Gelombang berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AdmissionWave $admissionWave)
    {
        Gate::authorize('admission-wave-edit');
        $this->ensureUnitAccess($admissionWave->school_unit_id);

        $admissionWave->load('admissionTracks');

        return view('admin.admission-waves.edit', array_merge(
            $this->formData(),
            compact('admissionWave')
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdmissionWaveRequest $request, AdmissionWave $admissionWave)
    {
        Gate::authorize('admission-wave-edit');
        $this->ensureUnitAccess($admissionWave->school_unit_id);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->admissionWaveService->updateAdmissionWave($admissionWave, $request->validated()), 'admin.admission-waves.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.admission-waves.index')
            ->with('success', 'Gelombang "'.$admissionWave->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdmissionWave $admissionWave)
    {
        Gate::authorize('admission-wave-delete');
        $this->ensureUnitAccess($admissionWave->school_unit_id);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->admissionWaveService->deleteAdmissionWave($admissionWave), 'admin.admission-waves.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.admission-waves.index')
            ->with('success', 'Gelombang berhasil dihapus.');
    }

    /**
     * Data shared by the create and edit forms.
     */
    private function formData(): array
    {
        return [
            'units' => $this->accessibleUnits(),
            'years' => AcademicYear::orderByDesc('name')->get(),
            'tracksByUnit' => AdmissionTrack::orderBy('name')->get()->groupBy('school_unit_id'),
        ];
    }
}
