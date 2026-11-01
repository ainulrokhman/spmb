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
     * Record a guardian's discount/waiver request on the invoice.
     */
    public function requestDiscount(Invoice $invoice, string $kind, ?string $type, ?int $value, string $reason): Invoice;

    /**
     * Decide a pending discount/waiver request (committee side).
     */
    public function decideDiscount(Invoice $invoice, bool $approve, User $decider, ?string $note): Invoice;

    /**
     * Settle the invoice and mark the application as re-registered.
     */
    public function markPaid(Invoice $invoice): Invoice;
}
