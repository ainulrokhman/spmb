<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ApplicationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
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

        return view('admin.form-payments.index', compact('pendingRequests', 'awaitingConfirmations'));
    }

    /**
     * Approve or reject a pending waiver / discount request.
     */
    public function decide(Request $request, Application $application)
    {
        $this->ensureUnitAccess($request, $application->school_unit_id);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->applications->decideFeeRequest(
                $application,
                $data['decision'] === 'approve',
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan biaya formulir telah diputuskan.');
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
