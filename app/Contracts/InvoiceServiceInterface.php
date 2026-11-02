<?php

namespace App\Contracts;

use App\Models\Application;
use App\Models\Invoice;
use App\Models\User;

interface InvoiceServiceInterface
{
    /**
     * The resolved draft lines for an application's re-registration
     * invoice: per fee of the unit, its mode and — for fixed fees —
     * the resolved amount, for pilihan fees the choosable tiers, and
     * for bebas fees the minimum.
     *
     * @return list<array<string, mixed>>
     */
    public function draftLines(Application $application): array;

    /**
     * Freeze the invoice from the guardian's selections (pilihan
     * tier choices and bebas amounts). One invoice per application.
     *
     * @param  array<int, array<string, mixed>>  $selections  keyed by fee id
     */
    public function createInvoice(Application $application, array $selections): Invoice;

    /**
     * Record a guardian's relief request on the invoice. The
     * guardian only states a reason; the committee decides the
     * form and size of the relief.
     */
    public function requestDiscount(Invoice $invoice, string $reason): Invoice;

    /**
     * Decide a pending relief request (committee side). When
     * approving, the committee chooses the relief itself: 'waiver'
     * (full), 'percent' or 'nominal' with its value.
     */
    public function decideDiscount(Invoice $invoice, bool $approve, ?string $reliefType, ?int $reliefValue, User $decider, ?string $note): Invoice;

    /**
     * Grant invoice relief directly, without a guardian request
     * (committee initiative).
     */
    public function grantDiscount(Invoice $invoice, string $reliefType, ?int $reliefValue, User $decider, ?string $note): Invoice;

    /**
     * Settle the invoice and mark the application as re-registered.
     */
    public function markPaid(Invoice $invoice): Invoice;
}
