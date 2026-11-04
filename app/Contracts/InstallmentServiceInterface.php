<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\User;

interface InstallmentServiceInterface
{
    /**
     * Record a guardian's installment request on the invoice. The
     * guardian only states a reason; the committee details the
     * terms when approving.
     */
    public function requestInstallment(Invoice $invoice, string $reason): Invoice;

    /**
     * Decide a pending installment request (committee side). When
     * approving, the committee provides the term schedule. Each
     * term carries a 'due_date' and either an 'items' map
     * (invoice item id => amount) detailing the term per
     * component — the per-component totals must equal each
     * component's payable share — or a bare 'amount', which is
     * then allocated over the components greedily.
     *
     * $decision is 'approve' (with the schedule), 'revision'
     * (handed back to the guardian to fix and resubmit; a note
     * saying what to fix is required) or 'reject' (final).
     *
     * @param  list<array<string, mixed>>|null  $schedule
     */
    public function decideInstallmentRequest(Invoice $invoice, string $decision, ?array $schedule, User $decider, ?string $note): Invoice;

    /**
     * Revise an existing plan (committee side) and optionally
     * add new terms in the same save. Settled terms and terms
     * with a payment in flight are locked; every still-unpaid
     * term must be present in $rows. Rows and new terms follow
     * the same shape as the approval schedule (per-component
     * 'items' maps, or bare amounts that are re-allocated
     * greedily). After the save, terms are re-sequenced by
     * due date.
     *
     * @param  array<int, array<string, mixed>>  $rows  keyed by installment id
     * @param  list<array<string, mixed>>  $newTerms
     */
    public function reviseSchedule(Invoice $invoice, array $rows, array $newTerms = []): Invoice;

    /**
     * Fill the per-component breakdown of every term that does
     * not have one yet, greedily in sequence order against the
     * components' remaining payable shares. Used for plans
     * created from amount-only schedules and to backfill plans
     * that predate the breakdown.
     */
    public function allocateTermItems(Invoice $invoice): void;

    /**
     * Settle one term after its payment is confirmed; when every
     * term is settled the invoice itself is marked paid.
     */
    public function settleInstallment(InvoiceInstallment $installment): InvoiceInstallment;
}
