<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        private readonly InstallmentServiceInterface $installments,
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

        $invoice->load(['items', 'application.user', 'application.schoolUnit', 'application.admissionWave', 'application.admissionTrack', 'payments', 'decider', 'installments.payments', 'installmentDecider']);

        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Approve or reject a pending relief request. When approving,
     * the committee chooses the relief (full waiver, or a percent
     * / nominal discount with its value).
     */
    public function decideDiscount(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'relief_type' => ['nullable', Rule::in(['waiver', 'percent', 'nominal'])],
            'relief_value' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->invoices->decideDiscount(
                $invoice,
                $data['decision'] === 'approve',
                $data['relief_type'] ?? null,
                isset($data['relief_value']) ? (int) $data['relief_value'] : null,
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan tagihan telah diputuskan.');
    }

    /**
     * Grant invoice relief directly, without a guardian request.
     */
    public function grantDiscount(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $data = $request->validate([
            'relief_type' => ['required', Rule::in(['waiver', 'percent', 'nominal'])],
            'relief_value' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->invoices->grantDiscount(
                $invoice,
                $data['relief_type'],
                isset($data['relief_value']) ? (int) $data['relief_value'] : null,
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Keringanan tagihan diberikan.');
    }

    /**
     * Approve or reject a pending installment request. When
     * approving, the committee details the term schedule.
     */
    public function decideInstallment(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
            'installments' => ['nullable', 'array'],
            'installments.*.amount' => ['required_with:installments', 'integer', 'min:1'],
            'installments.*.due_date' => ['required_with:installments', 'date'],
        ]);

        $schedule = null;

        if (($data['decision'] ?? null) === 'approve' && ! empty($data['installments'])) {
            $schedule = array_values(array_map(
                fn (array $term) => ['amount' => (int) $term['amount'], 'due_date' => (string) $term['due_date']],
                $data['installments'],
            ));
        }

        try {
            $this->installments->decideInstallmentRequest(
                $invoice,
                $data['decision'] === 'approve',
                $schedule,
                $request->user(),
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan cicilan telah diputuskan.');
    }

    /**
     * Revise the unpaid terms of an existing installment plan.
     */
    public function reviseInstallments(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.amount' => ['required', 'integer', 'min:1'],
            'rows.*.due_date' => ['required', 'date'],
        ]);

        $rows = [];

        foreach ($data['rows'] as $id => $row) {
            $rows[(int) $id] = ['amount' => (int) $row['amount'], 'due_date' => (string) $row['due_date']];
        }

        try {
            $this->installments->reviseSchedule($invoice, $rows);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rincian cicilan diperbarui.');
    }

    /**
     * Confirm a manual invoice payment the guardian declared.
     */
    public function confirm(Request $request, Payment $payment)
    {
        if ($payment->payable instanceof Invoice) {
            $this->ensureUnitAccess($request, $payment->payable);
        } elseif ($payment->payable instanceof InvoiceInstallment) {
            $this->ensureUnitAccess($request, $payment->payable->invoice);
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
