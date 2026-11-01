<?php

namespace App\Http\Controllers\Guardian;

use App\Contracts\InvoiceServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Guardian re-registration (Bab 5): build the invoice from the
 * unit's fee mapping, request a discount, and pay it through the
 * same channels as the form fee.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoices,
        private readonly PaymentService $payments,
    ) {}

    /**
     * The daftar ulang page: the fee draft (choices for pilihan and
     * bebas components) before the invoice exists, then the frozen
     * invoice with its payment step.
     */
    public function show(Application $application)
    {
        $this->ensureOwner($application);

        if (! in_array($application->status, ['passed', 're_registered'], true)) {
            return redirect()->route('guardian.applications.show', $application);
        }

        if ($application->status === 'passed' && ! $application->admissionWave->resultsAnnounced()) {
            return redirect()->route('guardian.dashboard')
                ->with('error', 'Hasil pendaftaran belum diumumkan untuk gelombang ini.');
        }

        $invoice = $application->invoice?->load(['items', 'payments']);
        $draftLines = $invoice === null ? $this->invoices->draftLines($application) : [];
        $channels = $invoice === null ? [] : $this->payments->enabledChannels();
        $activePayment = $invoice?->payments
            ->whereIn('status', ['pending', 'awaiting_confirmation'])
            ->sortByDesc('id')
            ->first();

        return view('guardian.invoices.show', compact('application', 'invoice', 'draftLines', 'channels', 'activePayment'));
    }

    /**
     * Freeze the invoice from the guardian's selections.
     */
    public function store(Request $request, Application $application)
    {
        $this->ensureOwner($application);

        $selections = [];

        foreach ((array) $request->input('choice', []) as $feeId => $amount) {
            $selections[(int) $feeId]['choice'] = (int) $amount;
        }

        foreach ((array) $request->input('free', []) as $feeId => $amount) {
            $selections[(int) $feeId]['free'] = (int) $amount;
        }

        try {
            $this->invoices->createInvoice($application, $selections);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('guardian.invoices.show', $application)
            ->with('success', 'Tagihan daftar ulang dibuat. Selesaikan pembayaran untuk menyelesaikan daftar ulang.');
    }

    /**
     * Start (or resume) the invoice payment on the chosen channel.
     */
    public function pay(Request $request, Application $application)
    {
        $this->ensureOwner($application);

        $invoice = $this->invoiceOf($application);

        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys($this->payments->enabledChannels()))],
        ]);

        try {
            $result = $this->payments->startInvoicePayment($invoice, $data['channel']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('guardian.invoices.show', $application)
            ->with('payment_view', $result['view'])
            ->with('success', 'Pembayaran dibuat. Selesaikan pembayaran agar daftar ulang terkonfirmasi.');
    }

    /**
     * The guardian declares a manual invoice payment as paid; the
     * committee still confirms it on their side.
     */
    public function declarePaid(Application $application, Payment $payment)
    {
        $this->ensureOwner($application);

        if ($payment->payable_type !== Invoice::class
            || (int) $payment->payable_id !== $application->invoice?->id) {
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
     * Request a discount or full waiver on the invoice.
     */
    public function requestDiscount(Request $request, Application $application)
    {
        $this->ensureOwner($application);

        $invoice = $this->invoiceOf($application);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['waiver', 'discount'])],
            'type' => ['nullable', Rule::in(array_keys(Application::DISCOUNT_TYPES))],
            'value' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($data['kind'] === 'discount') {
            if (empty($data['type'])) {
                return back()->with('error', 'Pilih tipe diskon (persen atau nominal).')->withInput();
            }
            if ($data['type'] === 'percent' && (int) $data['value'] > 100) {
                return back()->with('error', 'Diskon persen tidak boleh lebih dari 100%.')->withInput();
            }
        }

        try {
            $this->invoices->requestDiscount(
                $invoice,
                $data['kind'],
                $data['type'] ?? null,
                isset($data['value']) ? (int) $data['value'] : null,
                $data['reason'],
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan terkirim. Panitia akan memutuskan pengajuan Anda.');
    }

    private function invoiceOf(Application $application): Invoice
    {
        $invoice = $application->invoice;

        if ($invoice === null) {
            abort(404);
        }

        return $invoice;
    }

    private function ensureOwner(Application $application): void
    {
        abort_unless($application->user_id === auth()->id(), 404);
    }
}
