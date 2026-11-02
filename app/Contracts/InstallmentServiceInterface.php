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
     * approving, the committee provides the term schedule: a list
     * of ['amount' => int, 'due_date' => string] whose amounts
     * must total the invoice's payable amount.
     *
     * @param  list<array{amount: int, due_date: string}>|null  $schedule
     */
    public function decideInstallmentRequest(Invoice $invoice, bool $approve, ?array $schedule, User $decider, ?string $note): Invoice;

    /**
     * Revise the unpaid terms of an existing plan (committee
     * side). Paid terms are locked; the unpaid amounts must keep
     * totalling the invoice's remaining payable amount.
     *
     * @param  array<int, array{amount: int, due_date: string}>  $rows  keyed by installment id
     */
    public function reviseSchedule(Invoice $invoice, array $rows): Invoice;

    /**
     * Settle one term after its payment is confirmed; when every
     * term is settled the invoice itself is marked paid.
     */
    public function settleInstallment(InvoiceInstallment $installment): InvoiceInstallment;
}
