<?php

namespace App\Services;

use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Installment plans (cicilan) for re-registration invoices: the
 * guardian requests with a reason, the committee approves and
 * details the terms, and each term is then paid in full through
 * the regular payment channels.
 */
class InstallmentService implements InstallmentServiceInterface
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoices,
    ) {}

    /**
     * Record a guardian's installment request on the invoice.
     */
    public function requestInstallment(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas.');
        }

        if ($invoice->installments()->exists()) {
            throw new InvalidArgumentException('Tagihan ini sudah memiliki rincian cicilan.');
        }

        if ($invoice->installment_request_status === 'pending') {
            throw new InvalidArgumentException('Masih ada pengajuan cicilan yang menunggu keputusan panitia.');
        }

        if ($invoice->request_status === 'pending') {
            throw new InvalidArgumentException('Masih ada pengajuan keringanan yang menunggu keputusan panitia.');
        }

        $invoice->update([
            'installment_request_reason' => $reason,
            'installment_request_status' => 'pending',
            'installment_decision_note' => null,
        ]);

        return $invoice;
    }

    /**
     * Decide a pending installment request (committee side). When
     * approving, the committee provides the term schedule whose
     * amounts must total the invoice's payable amount.
     *
     * @param  list<array{amount: int, due_date: string}>|null  $schedule
     */
    public function decideInstallmentRequest(Invoice $invoice, bool $approve, ?array $schedule, User $decider, ?string $note): Invoice
    {
        if ($invoice->installment_request_status !== 'pending') {
            throw new InvalidArgumentException('Tidak ada pengajuan cicilan yang menunggu keputusan.');
        }

        $invoice->installment_request_status = $approve ? 'approved' : 'rejected';
        $invoice->installment_decision_note = $note;
        $invoice->installment_decided_by = $decider->id;
        $invoice->installment_decided_at = now();

        if (! $approve) {
            $invoice->save();

            return $invoice;
        }

        $terms = $this->validatedSchedule($schedule, $invoice->payableAmount());

        DB::transaction(function () use ($invoice, $terms) {
            $invoice->save();

            foreach ($terms as $index => $term) {
                $invoice->installments()->create([
                    'sequence' => $index + 1,
                    'amount' => $term['amount'],
                    'due_date' => $term['due_date'],
                    'status' => 'unpaid',
                ]);
            }
        });

        return $invoice;
    }

    /**
     * Revise the unpaid terms of an existing plan (committee
     * side). Paid terms are locked; the unpaid amounts must keep
     * totalling the invoice's remaining payable amount.
     *
     * @param  array<int, array{amount: int, due_date: string}>  $rows  keyed by installment id
     */
    public function reviseSchedule(Invoice $invoice, array $rows): Invoice
    {
        $installments = $invoice->installments()->get()->keyBy('id');

        if ($installments->isEmpty()) {
            throw new InvalidArgumentException('Tagihan ini belum memiliki rincian cicilan.');
        }

        $paidTotal = 0;
        $newUnpaidTotal = 0;

        foreach ($installments as $installment) {
            if ($installment->status === 'paid') {
                if (isset($rows[$installment->id])) {
                    throw new InvalidArgumentException('Termin yang sudah lunas tidak dapat diubah.');
                }

                $paidTotal += $installment->amount;

                continue;
            }

            if (! isset($rows[$installment->id])) {
                throw new InvalidArgumentException('Setiap termin yang belum lunas harus disertakan dalam rincian.');
            }

            $amount = (int) ($rows[$installment->id]['amount'] ?? 0);
            $dueDate = (string) ($rows[$installment->id]['due_date'] ?? '');

            if ($amount < 1 || $dueDate === '') {
                throw new InvalidArgumentException('Setiap termin memerlukan nominal dan tanggal jatuh tempo.');
            }

            $newUnpaidTotal += $amount;
        }

        if ($paidTotal + $newUnpaidTotal !== $invoice->payableAmount()) {
            throw new InvalidArgumentException('Total rincian cicilan harus sama dengan sisa tagihan Rp '.number_format($invoice->payableAmount(), 0, ',', '.').'.');
        }

        DB::transaction(function () use ($installments, $rows) {
            foreach ($rows as $id => $row) {
                $installments[$id]->update([
                    'amount' => (int) $row['amount'],
                    'due_date' => $row['due_date'],
                ]);
            }
        });

        return $invoice;
    }

    /**
     * Settle one term after its payment is confirmed; when every
     * term is settled the invoice itself is marked paid (which
     * also completes the re-registration).
     */
    public function settleInstallment(InvoiceInstallment $installment): InvoiceInstallment
    {
        $installment->update(['status' => 'paid', 'paid_at' => now()]);

        $invoice = $installment->invoice;

        if ($invoice !== null
            && $invoice->status !== 'paid'
            && ! $invoice->installments()->where('status', '!=', 'paid')->exists()) {
            $this->invoices->markPaid($invoice);
        }

        return $installment;
    }

    /**
     * Validate a committee-provided term schedule against the
     * amount it must total.
     *
     * @param  list<array{amount: int, due_date: string}>|null  $schedule
     * @return list<array{amount: int, due_date: string}>
     */
    private function validatedSchedule(?array $schedule, int $expectedTotal): array
    {
        if ($schedule === null || $schedule === []) {
            throw new InvalidArgumentException('Rincian termin cicilan wajib diisi saat pengajuan disetujui.');
        }

        $total = 0;

        foreach ($schedule as $term) {
            $amount = (int) ($term['amount'] ?? 0);
            $dueDate = (string) ($term['due_date'] ?? '');

            if ($amount < 1 || $dueDate === '') {
                throw new InvalidArgumentException('Setiap termin memerlukan nominal dan tanggal jatuh tempo.');
            }

            $total += $amount;
        }

        if ($total !== $expectedTotal) {
            throw new InvalidArgumentException('Total rincian cicilan harus sama dengan sisa tagihan Rp '.number_format($expectedTotal, 0, ',', '.').'.');
        }

        return array_values($schedule);
    }
}
