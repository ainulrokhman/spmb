<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\InvoiceServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Committee side of re-registration invoices: list per unit,
 * decide discount requests and confirm manual payments.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoices,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Invoice list, scoped to the units the committee member
     * serves and filterable by status.
     */
    public function index(Request $request)
    {
        $unitIds = $this->accessibleUnitIds($request);

        $invoices = Invoice::query()
            ->with(['application.user', 'application.schoolUnit', 'application.admissionWave'])
            ->whereHas('application', function ($query) use ($unitIds) {
                $query->when($unitIds !== null, fn ($q) => $q->whereIn('school_unit_id', $unitIds));
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $statuses = Invoice::STATUSES;

        return view('admin.invoices.index', compact('invoices', 'statuses'));
    }

    /**
     * Invoice detail: frozen items, discount request decision, and
     * manual payment confirmation.
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $invoice->load(['items', 'application.user', 'application.schoolUnit', 'application.admissionWave', 'application.admissionTrack', 'payments', 'decider']);

        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Approve or reject a pending discount/waiver request.
     */
    public function decideDiscount(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->invoices->decideDiscount(
                $invoice,
                $data['decision'] === 'approve',
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan tagihan telah diputuskan.');
    }

    /**
     * Confirm a manual invoice payment the guardian declared.
     */
    public function confirm(Request $request, Payment $payment)
    {
        if ($payment->payable instanceof Invoice) {
            $this->ensureUnitAccess($request, $payment->payable);
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

    private function ensureUnitAccess(Request $request, Invoice $invoice): void
    {
        abort_unless($request->user()->hasUnitAccess($invoice->application->school_unit_id), 403);
    }
}
