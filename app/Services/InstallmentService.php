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
    /**
     * Term statuses that can no longer be revised: settled, or
     * with a payment already in flight (started or declared
     * and awaiting confirmation).
     *
     * @var list<string>
     */
    private const LOCKED_STATUSES = ['paid', 'awaiting_payment', 'awaiting_confirmation'];

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
     * approving, the committee provides the term schedule: each
     * term details its per-component breakdown ('items'), whose
     * per-component totals must equal the components' payable
     * shares; a bare-'amount' schedule keeps working and is
     * allocated over the components greedily.
     *
     * @param  list<array<string, mixed>>|null  $schedule
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

        $matrix = $this->scheduleUsesItems($schedule);
        $terms = $matrix
            ? $this->validatedMatrixSchedule($invoice, $schedule ?? [])
            : $this->validatedSchedule($schedule, $invoice->payableAmount());

        DB::transaction(function () use ($invoice, $terms, $matrix) {
            $invoice->save();

            foreach ($terms as $index => $term) {
                $installment = $invoice->installments()->create([
                    'sequence' => $index + 1,
                    'amount' => $term['amount'],
                    'due_date' => $term['due_date'],
                    'status' => 'unpaid',
                ]);

                if ($matrix) {
                    $this->storeBreakdown($installment, $term['items']);
                }
            }

            if (! $matrix) {
                $this->allocateTermItems($invoice->fresh());
            }
        });

        return $invoice;
    }

    /**
     * Revise an existing plan (committee side) and optionally
     * add new terms in the same save. Settled terms and terms
     * with a payment in flight are locked. Terms may be given
     * as per-component 'items' maps (validated against the
     * components' payable shares) or as bare amounts (totals
     * must stay exact; the editable terms' breakdowns are then
     * rebuilt greedily). Terms are re-sequenced by due date.
     *
     * @param  array<int, array<string, mixed>>  $rows  keyed by installment id
     * @param  list<array<string, mixed>>  $newTerms
     */
    public function reviseSchedule(Invoice $invoice, array $rows, array $newTerms = []): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas; rincian cicilan tidak dapat diubah.');
        }

        $installments = $invoice->installments()->with('items')->get()->keyBy('id');

        if ($installments->isEmpty()) {
            throw new InvalidArgumentException('Tagihan ini belum memiliki rincian cicilan.');
        }

        $locked = $installments->filter(fn (InvoiceInstallment $installment) => in_array($installment->status, self::LOCKED_STATUSES, true));
        $editable = $installments->reject(fn (InvoiceInstallment $installment) => in_array($installment->status, self::LOCKED_STATUSES, true));

        foreach ($locked as $installment) {
            if (isset($rows[$installment->id])) {
                throw new InvalidArgumentException($installment->status === 'paid'
                    ? 'Termin yang sudah lunas tidak dapat diubah.'
                    : 'Termin yang sedang menunggu pembayaran/konfirmasi tidak dapat diubah.');
            }
        }

        foreach ($editable as $installment) {
            if (! isset($rows[$installment->id])) {
                throw new InvalidArgumentException('Setiap termin yang belum lunas harus disertakan dalam rincian.');
            }
        }

        foreach (array_keys($rows) as $id) {
            if (! $installments->has($id)) {
                throw new InvalidArgumentException('Termin tidak dikenal pada rincian cicilan.');
            }
        }

        if ($this->scheduleUsesItems(array_merge(array_values($rows), $newTerms))) {
            $this->reviseMatrix($invoice, $editable, $locked, $rows, $newTerms);
        } else {
            $this->reviseAmounts($invoice, $editable, $locked, $rows, $newTerms);
        }

        return $invoice;
    }

    /**
     * Fill the per-component breakdown of every term that does
     * not have one yet: terms in sequence order draw from each
     * component's remaining payable share, components in
     * invoice order. Terms that already carry a breakdown keep
     * it and consume their share first.
     */
    public function allocateTermItems(Invoice $invoice): void
    {
        $installments = $invoice->installments()->with('items')->orderBy('sequence')->orderBy('id')->get();

        if ($installments->isEmpty()) {
            return;
        }

        $remaining = $invoice->componentPayableShares();

        foreach ($installments as $installment) {
            foreach ($installment->items as $row) {
                $remaining[$row->invoice_item_id] = ($remaining[$row->invoice_item_id] ?? 0) - $row->amount;
            }
        }

        DB::transaction(function () use ($installments, $remaining) {
            foreach ($installments as $installment) {
                if ($installment->items->isNotEmpty()) {
                    continue;
                }

                $left = $installment->amount;

                foreach ($remaining as $itemId => $shareLeft) {
                    if ($left <= 0) {
                        break;
                    }

                    if ($shareLeft <= 0) {
                        continue;
                    }

                    $give = min($shareLeft, $left);
                    $installment->items()->create(['invoice_item_id' => $itemId, 'amount' => $give]);
                    $remaining[$itemId] -= $give;
                    $left -= $give;
                }
            }
        });
    }

    /**
     * Apply a per-component revision: locked terms contribute
     * their stored breakdowns; rows and new terms must make
     * every component total its payable share exactly.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $newTerms
     */
    private function reviseMatrix(Invoice $invoice, $editable, $locked, array $rows, array $newTerms): void
    {
        $shares = $invoice->componentPayableShares();
        $sums = array_fill_keys(array_keys($shares), 0);

        foreach ($locked as $installment) {
            foreach ($installment->items as $row) {
                $sums[$row->invoice_item_id] = ($sums[$row->invoice_item_id] ?? 0) + $row->amount;
            }
        }

        $normalizedRows = [];

        foreach ($editable as $installment) {
            $normalizedRows[$installment->id] = $this->normalizeMatrixTerm($rows[$installment->id], $shares, $sums);
        }

        $normalizedNew = [];

        foreach ($newTerms as $term) {
            $normalizedNew[] = $this->normalizeMatrixTerm($term, $shares, $sums);
        }

        $this->assertComponentTotals($invoice, $shares, $sums);

        DB::transaction(function () use ($invoice, $editable, $normalizedRows, $normalizedNew) {
            foreach ($normalizedRows as $id => $term) {
                $installment = $editable[$id];
                $installment->update(['amount' => $term['amount'], 'due_date' => $term['due_date']]);
                $installment->items()->delete();
                $this->storeBreakdown($installment, $term['items']);
            }

            $sequence = (int) $invoice->installments()->max('sequence');

            foreach ($normalizedNew as $term) {
                $installment = $invoice->installments()->create([
                    'sequence' => ++$sequence,
                    'amount' => $term['amount'],
                    'due_date' => $term['due_date'],
                    'status' => 'unpaid',
                ]);
                $this->storeBreakdown($installment, $term['items']);
            }

            $this->resequence($invoice);
        });
    }

    /**
     * Apply an amount-only revision: the grand total must stay
     * exact, then the editable terms' breakdowns are rebuilt
     * greedily so they keep matching the component shares.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $newTerms
     */
    private function reviseAmounts(Invoice $invoice, $editable, $locked, array $rows, array $newTerms): void
    {
        $total = (int) $locked->sum('amount');
        $normalizedNew = [];

        foreach ($editable as $installment) {
            $amount = (int) ($rows[$installment->id]['amount'] ?? 0);
            $dueDate = (string) ($rows[$installment->id]['due_date'] ?? '');

            if ($amount < 1 || $dueDate === '') {
                throw new InvalidArgumentException('Setiap termin memerlukan nominal dan tanggal jatuh tempo.');
            }

            $total += $amount;
        }

        foreach ($newTerms as $term) {
            $amount = (int) ($term['amount'] ?? 0);
            $dueDate = (string) ($term['due_date'] ?? '');

            if ($amount < 1 || $dueDate === '') {
                throw new InvalidArgumentException('Setiap termin memerlukan nominal dan tanggal jatuh tempo.');
            }

            $total += $amount;
            $normalizedNew[] = ['amount' => $amount, 'due_date' => $dueDate];
        }

        if ($total !== $invoice->payableAmount()) {
            throw new InvalidArgumentException('Total rincian cicilan harus sama dengan sisa tagihan Rp '.number_format($invoice->payableAmount(), 0, ',', '.').'.');
        }

        DB::transaction(function () use ($invoice, $editable, $rows, $normalizedNew) {
            foreach ($editable as $installment) {
                $installment->update([
                    'amount' => (int) $rows[$installment->id]['amount'],
                    'due_date' => (string) $rows[$installment->id]['due_date'],
                ]);
                $installment->items()->delete();
            }

            $sequence = (int) $invoice->installments()->max('sequence');

            foreach ($normalizedNew as $term) {
                $invoice->installments()->create([
                    'sequence' => ++$sequence,
                    'amount' => $term['amount'],
                    'due_date' => $term['due_date'],
                    'status' => 'unpaid',
                ]);
            }

            $this->resequence($invoice);
            $this->allocateTermItems($invoice->fresh());
        });
    }

    /**
     * Whether a schedule details its terms per component. A
     * schedule mixing detailed and bare-amount terms is
     * rejected: one save speaks one language.
     *
     * @param  list<array<string, mixed>>|null  $schedule
     */
    private function scheduleUsesItems(?array $schedule): bool
    {
        if ($schedule === null || $schedule === []) {
            return false;
        }

        $withItems = 0;

        foreach ($schedule as $term) {
            if (isset($term['items']) && is_array($term['items']) && $term['items'] !== []) {
                $withItems++;
            }
        }

        if ($withItems > 0 && $withItems < count($schedule)) {
            throw new InvalidArgumentException('Setiap termin harus dirinci per komponen; jangan mencampur rincian per komponen dengan nominal saja.');
        }

        return $withItems > 0;
    }

    /**
     * Validate a per-component schedule for a new plan and
     * normalize its terms.
     *
     * @param  list<array<string, mixed>>  $schedule
     * @return list<array{due_date: string, items: array<int, int>, amount: int}>
     */
    private function validatedMatrixSchedule(Invoice $invoice, array $schedule): array
    {
        if ($schedule === []) {
            throw new InvalidArgumentException('Rincian termin cicilan wajib diisi saat pengajuan disetujui.');
        }

        $shares = $invoice->componentPayableShares();

        if ($shares === []) {
            throw new InvalidArgumentException('Tagihan tidak memiliki komponen untuk dicicil.');
        }

        $sums = array_fill_keys(array_keys($shares), 0);
        $terms = [];

        foreach ($schedule as $term) {
            $terms[] = $this->normalizeMatrixTerm($term, $shares, $sums);
        }

        $this->assertComponentTotals($invoice, $shares, $sums);

        return $terms;
    }

    /**
     * Normalize one per-component term and add its slices to
     * the running component sums.
     *
     * @param  array<int, int>  $shares
     * @param  array<int, int>  $sums
     * @return array{due_date: string, items: array<int, int>, amount: int}
     */
    private function normalizeMatrixTerm(array $term, array $shares, array &$sums): array
    {
        $dueDate = (string) ($term['due_date'] ?? '');

        if ($dueDate === '') {
            throw new InvalidArgumentException('Setiap termin memerlukan tanggal jatuh tempo.');
        }

        $items = [];

        foreach ((array) ($term['items'] ?? []) as $itemId => $amount) {
            $amount = (int) $amount;

            if ($amount < 0) {
                throw new InvalidArgumentException('Rincian komponen termin tidak boleh negatif.');
            }

            if ($amount === 0) {
                continue;
            }

            if (! array_key_exists((int) $itemId, $shares)) {
                throw new InvalidArgumentException('Komponen tidak dikenal pada rincian cicilan.');
            }

            $items[(int) $itemId] = $amount;
            $sums[(int) $itemId] = ($sums[(int) $itemId] ?? 0) + $amount;
        }

        $amount = array_sum($items);

        if ($amount < 1) {
            throw new InvalidArgumentException('Setiap termin memerlukan rincian komponen dengan total lebih dari nol.');
        }

        return ['due_date' => $dueDate, 'items' => $items, 'amount' => $amount];
    }

    /**
     * Every component's slices must total its payable share.
     *
     * @param  array<int, int>  $shares
     * @param  array<int, int>  $sums
     */
    private function assertComponentTotals(Invoice $invoice, array $shares, array $sums): void
    {
        $names = $invoice->items->pluck('component_name', 'id');

        foreach ($shares as $itemId => $share) {
            if (($sums[$itemId] ?? 0) !== $share) {
                throw new InvalidArgumentException(
                    'Total cicilan komponen '.($names[$itemId] ?? '').' harus Rp '.number_format($share, 0, ',', '.')
                    .' (sekarang Rp '.number_format($sums[$itemId] ?? 0, 0, ',', '.').').'
                );
            }
        }
    }

    /**
     * Store one term's per-component breakdown rows.
     *
     * @param  array<int, int>  $items
     */
    private function storeBreakdown(InvoiceInstallment $installment, array $items): void
    {
        foreach ($items as $itemId => $amount) {
            if ($amount > 0) {
                $installment->items()->create(['invoice_item_id' => $itemId, 'amount' => $amount]);
            }
        }
    }

    /**
     * Re-number the plan's terms by due date (then id), via
     * temporary negative sequences so the unique
     * invoice+sequence constraint is never tripped mid-way.
     */
    private function resequence(Invoice $invoice): void
    {
        $ordered = $invoice->installments()->get()
            ->sortBy([['due_date', 'asc'], ['id', 'asc']])
            ->values();

        foreach ($ordered as $installment) {
            $installment->update(['sequence' => -$installment->id]);
        }

        foreach ($ordered as $index => $installment) {
            $installment->update(['sequence' => $index + 1]);
        }
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
