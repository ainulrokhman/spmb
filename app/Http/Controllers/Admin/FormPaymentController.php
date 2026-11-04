<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ApplicationServiceInterface;
use App\DataTables\FormPaymentDataTable;
use App\Http\Controllers\Controller;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Payment;
use App\Models\SchoolUnit;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class FormPaymentController extends Controller
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
        private readonly PaymentService $payments,
    ) {}

    /**
     * The form payments list: every application in every
     * form-fee condition as a DataTable (urgent work first),
     * with per-row actions — confirm an awaiting manual
     * payment, decide or grant relief, open the applicant.
     * Scoped to the units the committee member serves.
     */
    public function index(Request $request, FormPaymentDataTable $dataTable)
    {
        if ($request->ajax()) {
            if (! $request->user()->hasGlobalUnitAccess()) {
                $request->merge(['unit_scope' => implode(',', $request->user()->accessibleUnitIds())]);
            }

            return $dataTable->make();
        }

        $unitIds = $this->accessibleUnitIds($request);
        $units = SchoolUnit::query()
            ->when($unitIds !== null, fn ($query) => $query->whereIn('id', $unitIds))
            ->orderBy('name')
            ->get();
        $waves = AdmissionWave::query()
            ->whereIn('school_unit_id', $units->pluck('id'))
            ->with(['academicYear', 'schoolUnit'])
            ->orderBy('opens_at')
            ->get();
        $feeStatuses = Application::FEE_STATUSES;
        $channels = Payment::CHANNELS;

        return view('admin.form-payments.index', compact('units', 'waves', 'feeStatuses', 'channels'));
    }

    /**
     * Decide a pending relief request: approve (the committee
     * chooses the relief — full waiver, or a percent / nominal
     * discount with its value), ask for a revision (the guardian
     * fixes and resubmits; the note is required so they know
     * what to fix), or reject it.
     */
    public function decide(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application->school_unit_id);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,revision,reject'],
            'relief_type' => ['nullable', Rule::in(['waiver', 'percent', 'nominal'])],
            'relief_value' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,revision'],
        ], [
            'note.required_if' => 'Catatan wajib diisi bila meminta revisi, agar wali tahu apa yang harus diperbaiki.',
        ]);

        try {
            $this->applications->decideFeeRequest(
                $application,
                $data['decision'],
                $data['relief_type'] ?? null,
                isset($data['relief_value']) ? (int) $data['relief_value'] : null,
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan biaya formulir telah diputuskan.');
    }

    /**
     * Grant form fee relief directly, without a guardian request.
     */
    public function grant(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application->school_unit_id);

        $data = $request->validate([
            'relief_type' => ['required', Rule::in(['waiver', 'percent', 'nominal'])],
            'relief_value' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->applications->grantFeeRelief(
                $application,
                $data['relief_type'],
                isset($data['relief_value']) ? (int) $data['relief_value'] : null,
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Keringanan biaya formulir diberikan.');
    }

    /**
     * Confirm a manual payment the guardian declared as paid.
     */
    public function confirm(Request $request, Payment $payment)
    {
        if ($payment->payable instanceof Application) {
            $this->ensureUnitAccess($request, $payment->payable->school_unit_id);
        }

        $this->payments->confirmPayment($payment);

        return back()->with('success', 'Pembayaran manual dikonfirmasi lunas.');
    }

    /**
     * Unit ids the current user may work with, or null for global
     * (foundation-level) access.
     *
     * @return list<int>|null
     */
    private function accessibleUnitIds(Request $request): ?array
    {
        $user = $request->user();

        return $user->hasGlobalUnitAccess() ? null : $user->accessibleUnitIds();
    }

    private function ensureUnitAccess(Request $request, int $schoolUnitId): void
    {
        abort_unless($request->user()->hasUnitAccess($schoolUnitId), 403);
    }
}
