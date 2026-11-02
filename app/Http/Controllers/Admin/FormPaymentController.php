<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ApplicationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
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
     * Committee worklist for the form fee stage: pending waiver /
     * discount requests and manual payments awaiting confirmation,
     * scoped to the units the committee member serves.
     */
    public function index(Request $request)
    {
        $unitIds = $this->accessibleUnitIds($request);

        $pendingRequests = Application::query()
            ->with(['user', 'schoolUnit', 'admissionWave'])
            ->where('form_fee_request_status', 'pending')
            ->when($unitIds !== null, fn ($query) => $query->whereIn('school_unit_id', $unitIds))
            ->oldest('updated_at')
            ->get();

        $grantable = Application::query()
            ->with(['user', 'schoolUnit', 'admissionWave'])
            ->where('status', 'awaiting_form_fee')
            ->where('form_fee_status', 'unpaid')
            ->whereNull('form_fee_discount_type')
            ->where(fn ($query) => $query->whereNull('form_fee_request_status')->orWhere('form_fee_request_status', '!=', 'pending'))
            ->when($unitIds !== null, fn ($query) => $query->whereIn('school_unit_id', $unitIds))
            ->oldest()
            ->get();

        $awaitingConfirmations = Payment::query()
            ->with(['payable.user', 'payable.schoolUnit'])
            ->where('status', 'awaiting_confirmation')
            ->where('channel', 'manual')
            ->whereHasMorph('payable', [Application::class], function ($query) use ($unitIds) {
                if ($unitIds !== null) {
                    $query->whereIn('school_unit_id', $unitIds);
                }
            })
            ->oldest()
            ->get();

        return view('admin.form-payments.index', compact('pendingRequests', 'grantable', 'awaitingConfirmations'));
    }

    /**
     * Approve or reject a pending relief request. When approving,
     * the committee chooses the relief (full waiver, or a percent
     * / nominal discount with its value).
     */
    public function decide(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application->school_unit_id);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'relief_type' => ['nullable', Rule::in(['waiver', 'percent', 'nominal'])],
            'relief_value' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->applications->decideFeeRequest(
                $application,
                $data['decision'] === 'approve',
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
