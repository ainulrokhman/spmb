<?php

namespace App\Contracts;

use App\Models\Application;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * In-app notifications for officers (petugas): guardians'
 * requests and manual payment declarations notify the
 * officers who can decide them; a decision marks every
 * officer's copy of the event as read.
 */
interface OfficerNotificationServiceInterface
{
    /**
     * Notify the form-fee deciders of the application's unit
     * about a new form-fee relief request.
     */
    public function notifyFormFeeReliefRequested(Application $application): void;

    /**
     * Notify the invoice managers of the application's unit
     * about a new invoice relief request.
     */
    public function notifyInvoiceReliefRequested(Invoice $invoice): void;

    /**
     * Notify the invoice managers of the application's unit
     * about a new installment request.
     */
    public function notifyInstallmentRequested(Invoice $invoice): void;

    /**
     * Notify the relevant deciders that a manual payment
     * (form fee, invoice, or installment term) was declared
     * and awaits confirmation.
     */
    public function notifyManualPaymentDeclared(Payment $payment): void;

    /**
     * Mark every officer's copy of the form-fee relief
     * request event as read (it has been decided).
     */
    public function markFormFeeReliefHandled(Application $application): void;

    /**
     * Mark every officer's copy of the invoice relief
     * request event as read (it has been decided).
     */
    public function markInvoiceReliefHandled(Invoice $invoice): void;

    /**
     * Mark every officer's copy of the installment request
     * event as read (it has been decided).
     */
    public function markInstallmentHandled(Invoice $invoice): void;

    /**
     * Mark every officer's copy of the manual payment
     * declaration event as read (it has been resolved).
     */
    public function markManualPaymentHandled(Payment $payment): void;
}
