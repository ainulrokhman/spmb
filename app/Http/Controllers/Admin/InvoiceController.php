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

        $invoice->load(['items', 'application.user', 'application.schoolUnit', 'application.admissionWave', 'application.admissionTrack', 'payments', 'decider', 'installments.payments', 'installments.items.invoiceItem', 'installmentDecider']);

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
            'installments.*.amount' => ['nullable', 'integer', 'min:1'],
            'installments.*.due_date' => ['required_with:installments', 'date'],
            'installments.*.items' => ['nullable', 'array'],
            'installments.*.items.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $schedule = null;

        if (($data['decision'] ?? null) === 'approve' && ! empty($data['installments'])) {
            $schedule = array_values(array_map(
                fn (array $term) => $this->normalizeTermInput($term),
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
     * Revise the unpaid terms of an existing installment plan
     * and optionally add new terms in the same save. Terms are
     * submitted as per-component breakdowns (rows / new_terms).
     */
    public function reviseInstallments(Request $request, Invoice $invoice)
    {
        $this->ensureUnitAccess($request, $invoice);

        $data = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.amount' => ['nullable', 'integer', 'min:1'],
            'rows.*.due_date' => ['nullable', 'date'],
            'rows.*.items' => ['nullable', 'array'],
            'rows.*.items.*' => ['nullable', 'integer', 'min:0'],
            'new_terms' => ['nullable', 'array'],
            'new_terms.*.amount' => ['nullable', 'integer', 'min:1'],
            'new_terms.*.due_date' => ['nullable', 'date'],
            'new_terms.*.items' => ['nullable', 'array'],
            'new_terms.*.items.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $rows = [];

        foreach (($data['rows'] ?? []) as $id => $row) {
            $rows[(int) $id] = $this->normalizeTermInput($row);
        }

        $newTerms = array_values(array_map(
            fn (array $term) => $this->normalizeTermInput($term),
            $data['new_terms'] ?? [],
        ));

        try {
            $this->installments->reviseSchedule($invoice, $rows, $newTerms);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rincian cicilan diperbarui.');
    }

    /**
     * Normalize one submitted term: its per-component 'items'
     * map when present, otherwise the bare amount.
     *
     * @param  array<string, mixed>  $term
     * @return array<string, mixed>
     */
    private function normalizeTermInput(array $term): array
    {
        $normalized = ['due_date' => (string) ($term['due_date'] ?? '')];

        if (isset($term['items']) && is_array($term['items'])) {
            $normalized['items'] = collect($term['items'])
                ->map(fn ($amount) => (int) $amount)
                ->all();
        } else {
            $normalized['amount'] = (int) ($term['amount'] ?? 0);
        }

        return $normalized;
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
