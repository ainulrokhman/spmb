<?php

namespace App\Http\Controllers\Guardian;

use App\Contracts\ApplicationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\SaveApplicationFormRequest;
use App\Http\Requests\Guardian\StartApplicationRequest;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\DocumentType;
use App\Models\Payment;
use App\Models\SchoolUnit;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Step zero: choose unit, wave, track (and major). Selection is
     * GET-driven (?unit=&wave=) so it works without JavaScript.
     */
    public function create(Request $request)
    {
        $units = SchoolUnit::query()
            ->with(['program', 'majors' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedUnit = $units->firstWhere('id', (int) $request->query('unit'));

        $waves = $selectedUnit === null
            ? collect()
            : $selectedUnit->admissionWaves()
                ->with(['academicYear', 'admissionTracks'])
                ->orderBy('opens_at')
                ->get()
                ->filter(fn (AdmissionWave $wave) => $wave->status() === AdmissionWave::STATUS_OPEN)
                ->values();

        $selectedWave = $waves->firstWhere('id', (int) $request->query('wave'));

        return view('guardian.applications.create', compact('units', 'selectedUnit', 'waves', 'selectedWave'));
    }

    /**
     * Start the application (freezes the form fee) and move to the
     * step the application starts on.
     */
    public function store(StartApplicationRequest $request)
    {
        $wave = AdmissionWave::findOrFail($request->validated('admission_wave_id'));
        $track = $wave->admissionTracks()->findOrFail($request->validated('admission_track_id'));
        $major = $request->validated('major_id')
            ? $wave->schoolUnit->majors()->findOrFail($request->validated('major_id'))
            : null;

        try {
            $application = $this->applications->start($request->user(), $wave, $track, $major);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('guardian.applications.show', $application)
            ->with('success', 'Pendaftaran dimulai. Selesaikan tahap pembayaran formulir untuk melanjutkan.');
    }

    /**
     * Route the guardian to the step their application is on; steps
     * cannot be opened out of order.
     */
    public function show(Application $application)
    {
        $this->ensureOwner($application);

        return redirect()->route(match ($application->status) {
            'awaiting_form_fee' => 'guardian.applications.payment',
            'filling_form' => 'guardian.applications.form',
            'uploading_documents', 'needs_revision' => 'guardian.applications.documents',
            'passed' => 'guardian.invoices.show',
            default => 'guardian.applications.proof',
        }, $application);
    }

    /**
     * Step: form fee payment.
     */
    public function payment(Application $application)
    {
        $this->ensureOwner($application);

        if ($application->status !== 'awaiting_form_fee') {
            return redirect()->route('guardian.applications.show', $application);
        }

        $channels = $this->payments->enabledChannels();
        $activePayment = $application->payments()
            ->whereIn('status', ['pending', 'awaiting_confirmation'])
            ->latest()
            ->first();

        return view('guardian.applications.payment', compact('application', 'channels', 'activePayment'));
    }

    /**
     * Start (or resume) the fee payment on the chosen channel.
     */
    public function pay(Request $request, Application $application)
    {
        $this->ensureOwner($application);

        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys($this->payments->enabledChannels()))],
        ]);

        try {
            $result = $this->payments->startPayment($application, $data['channel']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('guardian.applications.payment', $application)
            ->with('payment_view', $result['view'])
            ->with('success', 'Pembayaran dibuat. Selesaikan pembayaran agar formulir terbuka.');
    }

    /**
     * The guardian declares a manual payment as paid; the committee
     * still confirms it on their side.
     */
    public function declarePaid(Application $application, Payment $payment)
    {
        $this->ensureOwner($application);

        if ($payment->payable_type !== Application::class || (int) $payment->payable_id !== $application->id) {
            abort(404);
        }

        try {
            $this->payments->declareManualPaid($payment);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Terima kasih. Panitia akan mengonfirmasi pembayaran Anda.');
    }

    /**
     * Request form fee relief at the payment step. The guardian
     * only states a reason; the committee decides the form and
     * size of the relief.
     */
    public function requestRelief(Request $request, Application $application)
    {
        $this->ensureOwner($application);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->applications->requestFeeRelief($application, $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan terkirim. Panitia akan memutuskan pengajuan Anda.');
    }

    /**
     * Step: fill the form (core identity, parent identities, and
     * the unit's dynamic form fields, grouped by section).
     */
    public function form(Application $application)
    {
        $this->ensureOwner($application);

        if (! $application->isEditable()) {
            return redirect()->route('guardian.applications.show', $application);
        }

        $fields = $application->schoolUnit->formFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($field) => $field->section ?: 'Formulir Tambahan');

        $user = auth()->user();
        $relation = old('guardian_relation', $application->guardian_relation);

        // Pre-fill the identity section matching the declared
        // relation from the guardian account on first fill.
        $father = old('father', $application->father_data) ?? ($relation === 'ayah' ? ['name' => $user->name, 'phone' => $user->phone] : []);
        $mother = old('mother', $application->mother_data) ?? ($relation === 'ibu' ? ['name' => $user->name, 'phone' => $user->phone] : []);
        $guardian = old('guardian', $application->guardian_data) ?? ($relation === 'wali' ? ['name' => $user->name, 'phone' => $user->phone] : []);

        return view('guardian.applications.form', compact('application', 'fields', 'father', 'mother', 'guardian'));
    }

    /**
     * Save the form and advance to the documents step.
     */
    public function saveForm(SaveApplicationFormRequest $request, Application $application)
    {
        $data = $request->validated();

        // Dynamic file fields: store the upload and keep its path as
        // the answer; keep the previously stored path when no new
        // file is uploaded.
        $answers = $data['answers'] ?? [];
        $fileFields = $application->schoolUnit->formFields()
            ->where('is_active', true)
            ->where('type', 'file')
            ->get();

        foreach ($fileFields as $field) {
            if ($request->hasFile('answers.'.$field->key)) {
                $answers[$field->key] = $request->file('answers.'.$field->key)
                    ->store('formulir-pendaftaran/'.$application->id, 'public');
            } elseif (! empty($application->answers[$field->key] ?? null)) {
                $answers[$field->key] = $application->answers[$field->key];
            }

            if ($field->is_required && empty($answers[$field->key] ?? null)) {
                return back()->with('error', 'Bidang berkas wajib "'.$field->label.'" belum diunggah.')->withInput();
            }
        }

        $data['answers'] = $answers;

        try {
            $this->applications->saveForm($application, $data);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('guardian.applications.documents', $application)
            ->with('success', 'Formulir tersimpan. Lanjutkan dengan mengunggah berkas.');
    }

    /**
     * Step: upload the documents required by the unit.
     */
    public function documents(Application $application)
    {
        $this->ensureOwner($application);

        if (! in_array($application->status, ['uploading_documents', 'needs_revision'], true)) {
            return redirect()->route('guardian.applications.show', $application);
        }

        $requirements = $application->schoolUnit->documentRequirements()
            ->with('documentType')
            ->get()
            ->sortBy(fn ($requirement) => $requirement->requirement === 'required' ? 0 : 1)
            ->values();

        $uploaded = $application->documents()->get()->keyBy('document_type_id');
        $missing = $this->applications->missingRequiredDocuments($application);

        return view('guardian.applications.documents', compact('application', 'requirements', 'uploaded', 'missing'));
    }

    /**
     * Upload or replace one document.
     */
    public function uploadDocument(Request $request, Application $application, DocumentType $documentType)
    {
        $this->ensureOwner($application);

        if (! $application->isEditable() || ! in_array($application->status, ['uploading_documents', 'needs_revision'], true)) {
            return back()->with('error', 'Berkas belum dapat diunggah pada tahap ini.');
        }

        // Each document type configures its own allowed extensions
        // and size limit (Fase 1B master data).
        $extensions = $documentType->extensionList();
        $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $extensions), 'max:'.($documentType->max_size_kb ?: 2048)],
        ]);

        $this->applications->storeDocument($application, $documentType, $request->file('file'));

        return back()->with('success', 'Berkas "'.$documentType->name.'" tersimpan.');
    }

    /**
     * Delete one uploaded document.
     */
    public function deleteDocument(Application $application, DocumentType $documentType)
    {
        $this->ensureOwner($application);

        if (! $application->isEditable() || ! in_array($application->status, ['uploading_documents', 'needs_revision'], true)) {
            return back()->with('error', 'Berkas tidak dapat dihapus pada tahap ini.');
        }

        $this->applications->deleteDocument($application, $documentType);

        return back()->with('success', 'Berkas dihapus.');
    }

    /**
     * Send the application: issues the registration number and moves
     * to Menunggu Verifikasi.
     */
    public function submit(Application $application)
    {
        $this->ensureOwner($application);

        try {
            $this->applications->submit($application);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('guardian.applications.proof', $application)
            ->with('success', 'Pendaftaran terkirim. Nomor pendaftaran Anda: '.$application->registration_number);
    }

    /**
     * The printable proof of a submitted application.
     */
    public function proof(Application $application)
    {
        $this->ensureOwner($application);

        if ($application->registration_number === null) {
            return redirect()->route('guardian.applications.show', $application);
        }

        $application->load(['schoolUnit.program', 'admissionWave.academicYear', 'admissionTrack', 'major', 'documents.documentType']);

        return view('guardian.applications.proof', compact('application'));
    }

    /**
     * The printable filled registration form of a submitted
     * application (Bab 7).
     */
    public function printForm(Application $application)
    {
        $this->ensureOwner($application);

        if ($application->registration_number === null) {
            return redirect()->route('guardian.applications.show', $application);
        }

        $application->load(['user', 'schoolUnit.program', 'admissionWave.academicYear', 'admissionTrack', 'major', 'documents.documentType']);

        $formFields = $application->schoolUnit->formFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('guardian.applications.print', compact('application', 'formFields'));
    }

    /**
     * A guardian only ever sees their own applications.
     */
    private function ensureOwner(Application $application): void
    {
        abort_unless($application->user_id === auth()->id(), 404);
    }
}
