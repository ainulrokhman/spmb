<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\AdmissionTrackServiceInterface;
use App\DataTables\AdmissionTrackDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Admin\Concerns\ScopesUnits;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdmissionTrack\StoreAdmissionTrackRequest;
use App\Http\Requests\AdmissionTrack\UpdateAdmissionTrackRequest;
use App\Models\AdmissionTrack;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdmissionTrackController extends Controller
{
    use HandlesServiceExceptions, ScopesUnits;

    protected AdmissionTrackServiceInterface $admissionTrackService;

    /**
     * AdmissionTrackController constructor.
     */
    public function __construct(AdmissionTrackServiceInterface $admissionTrackService)
    {
        $this->admissionTrackService = $admissionTrackService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AdmissionTrackDataTable $admissionTrackDataTable)
    {
        Gate::authorize('admission-track-list');

        if ($request->ajax()) {
            $this->injectUnitScope($request);

            return $admissionTrackDataTable->make();
        }

        $units = $this->accessibleUnits();

        return view('admin.admission-tracks.index', compact('units'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('admission-track-create');

        $units = $this->accessibleUnits();

        return view('admin.admission-tracks.create', compact('units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdmissionTrackRequest $request)
    {
        Gate::authorize('admission-track-create');

        $admissionTrack = $this->admissionTrackService->createAdmissionTrack($request->validated());

        return redirect()->route('admin.admission-tracks.index')
            ->with('success', 'Jalur pendaftaran "'.$admissionTrack->name.'" berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AdmissionTrack $admissionTrack)
    {
        Gate::authorize('admission-track-edit');
        $this->ensureUnitAccess($admissionTrack->school_unit_id);

        $units = $this->accessibleUnits();

        return view('admin.admission-tracks.edit', compact('admissionTrack', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdmissionTrackRequest $request, AdmissionTrack $admissionTrack)
    {
        Gate::authorize('admission-track-edit');
        $this->ensureUnitAccess($admissionTrack->school_unit_id);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->admissionTrackService->updateAdmissionTrack($admissionTrack, $request->validated()), 'admin.admission-tracks.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.admission-tracks.index')
            ->with('success', 'Jalur pendaftaran "'.$admissionTrack->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdmissionTrack $admissionTrack)
    {
        Gate::authorize('admission-track-delete');
        $this->ensureUnitAccess($admissionTrack->school_unit_id);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->admissionTrackService->deleteAdmissionTrack($admissionTrack), 'admin.admission-tracks.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.admission-tracks.index')
            ->with('success', 'Jalur pendaftaran berhasil dihapus.');
    }
}
