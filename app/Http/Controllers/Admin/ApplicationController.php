<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ApplicationServiceInterface;
use App\DataTables\ApplicationDataTable;
use App\Http\Controllers\Controller;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
    ) {}

    /**
     * Verification worklist: submitted applications, filterable by
     * unit / wave / track / status and scoped to the committee
     * member's assigned units.
     */
    public function index(Request $request, ApplicationDataTable $dataTable)
    {
        if ($request->ajax()) {
            if (! $request->user()->hasGlobalUnitAccess()) {
                $request->merge(['unit_scope' => implode(',', $request->user()->accessibleUnitIds())]);
            }

            return $dataTable->make();
        }

        $units = $this->accessibleUnits($request);
        $waves = AdmissionWave::query()
            ->whereIn('school_unit_id', $units->pluck('id'))
            ->with('academicYear')
            ->orderBy('opens_at')
            ->get();
        $tracks = AdmissionTrack::query()
            ->whereIn('school_unit_id', $units->pluck('id'))
            ->orderBy('name')
            ->get();
        $statuses = array_intersect_key(Application::STATUSES, array_flip([
            'submitted', 'verified', 'needs_revision', 'rejected', 'passed', 'failed', 're_registered',
        ]));

        return view('admin.applications.index', compact('units', 'waves', 'tracks', 'statuses'));
    }

    /**
     * Application detail: child data, parent identities, dynamic
     * answers, fee summary, documents with per-document checks, and
     * the overall decision panel.
     */
    public function show(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application);

        $application->load([
            'user', 'schoolUnit.program', 'admissionWave.academicYear', 'admissionTrack', 'major',
            'documents.documentType', 'documents.verifier', 'verifier', 'payments',
        ]);

        $formFields = $application->schoolUnit->formFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $requirements = $application->schoolUnit->documentRequirements()
            ->with('documentType')
            ->get()
            ->keyBy('document_type_id');

        $unverifiedRequired = $application->status === 'submitted'
            ? $this->applications->unverifiedRequiredDocuments($application)
            : collect();

        return view('admin.applications.show', compact('application', 'formFields', 'requirements', 'unverifiedRequired'));
    }

    /**
     * The printable filled registration form (Bab 7).
     */
    public function print(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application);

        $application->load(['user', 'schoolUnit.program', 'admissionWave.academicYear', 'admissionTrack', 'major', 'documents.documentType']);

        $formFields = $application->schoolUnit->formFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('admin.applications.print', compact('application', 'formFields'));
    }

    /**
     * Record the check of one document (verified / revision).
     */
    public function verifyDocument(Request $request, Application $application, ApplicationDocument $document)
    {
        $this->ensureUnitAccess($request, $application);

        if ((int) $document->application_id !== $application->id) {
            abort(404);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'revision'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->applications->verifyDocument($document, $data['status'], $data['note'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pemeriksaan berkas tersimpan.');
    }

    /**
     * Record the overall decision (verified / needs_revision /
     * rejected).
     */
    public function decide(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'needs_revision', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->applications->decideVerification($application, $data['decision'], $data['note'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.applications.show', $application)
            ->with('success', 'Keputusan verifikasi tersimpan: '.$application->fresh()->statusLabel().'.');
    }

    /**
     * Announce results (Lulus / Tidak Lulus) for verified
     * applications: either the given ids, or every verified
     * application matching the unit/wave filters.
     */
    public function announce(Request $request)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['passed', 'failed'])],
            'application_ids' => ['nullable', 'array'],
            'application_ids.*' => ['integer'],
            'school_unit_id' => ['nullable', 'integer'],
            'admission_wave_id' => ['nullable', 'integer'],
        ]);

        $query = Application::query()->where('status', 'verified');

        if (! empty($data['application_ids'])) {
            $query->whereIn('id', $data['application_ids']);
        } else {
            $query->when($data['school_unit_id'] ?? null, fn ($q, $unit) => $q->where('school_unit_id', $unit))
                ->when($data['admission_wave_id'] ?? null, fn ($q, $wave) => $q->where('admission_wave_id', $wave));
        }

        $user = $request->user();

        if (! $user->hasGlobalUnitAccess()) {
            $query->whereIn('school_unit_id', $user->accessibleUnitIds());
        }

        $count = 0;

        foreach ($query->get() as $application) {
            $this->applications->decideAnnouncement($application, $data['decision']);
            $count++;
        }

        $label = $data['decision'] === 'passed' ? 'Lulus' : 'Tidak Lulus';

        return redirect()->route('admin.applications.index')
            ->with('success', $count." pendaftar ditetapkan {$label}.");
    }

    /**
     * Units the current user may work with.
     */
    private function accessibleUnits(Request $request)
    {
        $user = $request->user();

        return SchoolUnit::query()
            ->when(! $user->hasGlobalUnitAccess(), fn ($query) => $query->whereIn('id', $user->accessibleUnitIds()))
            ->orderBy('name')
            ->get();
    }

    private function ensureUnitAccess(Request $request, Application $application): void
    {
        abort_unless($request->user()->hasUnitAccess($application->school_unit_id), 403);
    }
}
