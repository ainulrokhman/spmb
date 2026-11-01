<?php

namespace App\Services;

use App\Contracts\FeeServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\Application;
use App\Models\Fee;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private readonly FeeServiceInterface $fees,
    ) {}

    /**
     * The resolved draft lines for an application's re-registration
     * invoice: per fee of the unit, its mode and — for fixed fees —
     * the resolved amount, for pilihan fees the choosable tiers, and
     * for bebas fees the minimum.
     *
     * @return list<array<string, mixed>>
     */
    public function draftLines(Application $application): array
    {
        $fees = Fee::query()
            ->with('feeComponent')
            ->where('school_unit_id', $application->school_unit_id)
            ->get()
            ->sortBy(fn (Fee $fee) => $fee->feeComponent?->code)
            ->values();

        $lines = [];

        foreach ($fees as $fee) {
            $line = [
                'fee' => $fee,
                'mode' => $fee->mode,
                'fixed_amount' => null,
                'options' => [],
                'min_amount' => $fee->min_amount,
            ];

            if ($fee->mode === Fee::MODE_FIXED) {
                $line['fixed_amount'] = $this->fees->resolveFixedAmount($fee, $application->admission_track_id, $application->gender) ?? 0;
            } elseif ($fee->mode === Fee::MODE_PILIHAN) {
                $line['options'] = $this->fees->resolveAmounts($fee, $application->admission_track_id, $application->gender)
                    ->pluck('amount')
                    ->map(fn ($amount) => (int) $amount)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Freeze the invoice from the guardian's selections (pilihan
     * tier choices and bebas amounts). One invoice per application.
     *
     * @param  array<int, array<string, mixed>>  $selections  keyed by fee id
     */
    public function createInvoice(Application $application, array $selections): Invoice
    {
        if ($application->status !== 'passed') {
            throw new InvalidArgumentException('Daftar ulang hanya untuk pendaftar yang dinyatakan lulus.');
        }

        if (! $application->admissionWave->resultsAnnounced()) {
            throw new InvalidArgumentException('Hasil pendaftaran belum diumumkan.');
        }

        $existing = $application->invoice()->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = $this->draftLines($application);

        return DB::transaction(function () use ($application, $lines, $selections) {
            $invoice = Invoice::create([
                'application_id' => $application->id,
                'total' => 0,
                'status' => 'unpaid',
            ]);

            $total = 0;

            foreach ($lines as $line) {
                /** @var Fee $fee */
                $fee = $line['fee'];
                $selection = $selections[$fee->id] ?? [];

                $amount = match ($fee->mode) {
                    Fee::MODE_FIXED => $line['fixed_amount'],
                    Fee::MODE_PILIHAN => $this->chosenTier($line, $selection),
                    Fee::MODE_BEBAS => $this->chosenFreeAmount($line, $selection),
                    default => 0,
                };

                $invoice->items()->create([
                    'fee_id' => $fee->id,
                    'component_code' => $fee->feeComponent?->code ?? '',
                    'component_name' => $fee->feeComponent?->name ?? '',
                    'mode' => $fee->mode,
                    'amount' => $amount,
                ]);

                $total += $amount;
            }

            $invoice->update(['total' => $total]);

            // A zero-total invoice needs no payment at all.
            if ($total === 0) {
                $this->markPaid($invoice);
            }

            return $invoice;
        });
    }

    /**
     * Record a guardian's discount/waiver request on the invoice.
     */
    public function requestDiscount(Invoice $invoice, string $kind, ?string $type, ?int $value, string $reason): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas.');
        }

        if ($invoice->request_status === 'pending') {
            throw new InvalidArgumentException('Masih ada pengajuan yang menunggu keputusan panitia.');
        }

        $invoice->update([
            'request_kind' => $kind,
            'request_type' => $kind === 'discount' ? $type : null,
            'request_value' => $kind === 'discount' ? $value : null,
            'request_reason' => $reason,
            'request_status' => 'pending',
            'decision_note' => null,
        ]);

        return $invoice;
    }

    /**
     * Decide a pending discount/waiver request (committee side).
     */
    public function decideDiscount(Invoice $invoice, bool $approve, User $decider, ?string $note): Invoice
    {
        if ($invoice->request_status !== 'pending') {
            throw new InvalidArgumentException('Tidak ada pengajuan yang menunggu keputusan.');
        }

        $invoice->request_status = $approve ? 'approved' : 'rejected';
        $invoice->decision_note = $note;
        $invoice->decided_by = $decider->id;
        $invoice->decided_at = now();

        if ($approve) {
            if ($invoice->request_kind === 'waiver') {
                $invoice->discount_type = 'percent';
                $invoice->discount_value = 100;
            } else {
                $invoice->discount_type = $invoice->request_type;
                $invoice->discount_value = $invoice->request_value;
            }

            if ($invoice->payableAmount() === 0) {
                $invoice->save();

                return $this->markPaid($invoice);
            }

            $invoice->status = 'unpaid';
        }

        $invoice->save();

        return $invoice;
    }

    /**
     * Settle the invoice and mark the application as re-registered.
     */
    public function markPaid(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        $application = $invoice->application;

        if ($application !== null && $application->status === 'passed') {
            $application->update(['status' => 're_registered']);
        }

        return $invoice;
    }

    /**
     * Validate and return the tier a guardian picked for a pilihan
     * fee.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $selection
     */
    private function chosenTier(array $line, array $selection): int
    {
        $amount = isset($selection['choice']) ? (int) $selection['choice'] : null;

        if ($amount === null || ! in_array($amount, $line['options'], true)) {
            throw new InvalidArgumentException('Pilih salah satu nominal untuk komponen '.$line['fee']->feeComponent?->name.'.');
        }

        return $amount;
    }

    /**
     * Validate and return the amount a guardian filled for a bebas
     * fee (never below the configured minimum).
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $selection
     */
    private function chosenFreeAmount(array $line, array $selection): int
    {
        $amount = isset($selection['free']) ? (int) $selection['free'] : 0;
        $minimum = $line['min_amount'] ?? 0;

        if ($amount < $minimum) {
            throw new InvalidArgumentException('Nominal '.$line['fee']->feeComponent?->name.' tidak boleh di bawah minimum Rp '.number_format($minimum, 0, ',', '.').'.');
        }

        return $amount;
    }
}
