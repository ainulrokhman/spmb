<?php

namespace App\Contracts;

use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * In-app notifications for guardians (wali): committee
 * decisions, verification outcomes, payment states, and
 * installment due-date reminders for their children's
 * applications. Recipients are the registering account
 * holder plus every linked parent account. Result (Lulus /
 * Tidak Lulus) notifications stay invisible until the
 * application's announcement time.
 */
interface GuardianNotificationServiceInterface
{
    /**
     * The committee decided the form-fee relief request (or
     * granted relief directly, $directGrant = true).
     */
    public function notifyFormReliefDecided(Application $application, bool $approved, bool $directGrant = false): void;

    /**
     * The committee decided the invoice relief request (or
     * granted relief directly, $directGrant = true).
     */
    public function notifyInvoiceReliefDecided(Invoice $invoice, bool $approved, bool $directGrant = false): void;

    /**
     * The committee decided the installment request.
     */
    public function notifyInstallmentDecided(Invoice $invoice, bool $approved): void;

    /**
     * The committee recorded a verification decision
     * (verified / needs_revision / rejected) on the
     * application; the note is included when present.
     */
    public function notifyVerificationDecided(Application $application): void;

    /**
     * The committee set the selection result (passed /
     * failed). The notification is created immediately but
     * only becomes visible at the announcement time.
     */
    public function notifyResultDecided(Application $application): void;

    /**
     * The form fee was settled by a confirmed payment.
     */
    public function notifyFormFeePaid(Application $application): void;

    /**
     * The re-registration invoice was settled in full.
     */
    public function notifyInvoicePaid(Invoice $invoice): void;

    /**
     * One installment term was settled while the invoice
     * still has a remaining balance.
     */
    public function notifyInstallmentPaid(InvoiceInstallment $installment): void;

    /**
     * A channel payment failed or expired; the payable can
     * be paid again.
     */
    public function notifyPaymentUnsuccessful(Payment $payment): void;

    /**
     * Remind the guardians about a term falling due in
     * $daysBefore days (0 = today). Each recipient is
     * reminded at most once per term and moment; returns
     * how many notifications were sent.
     */
    public function notifyInstallmentDue(InvoiceInstallment $installment, int $daysBefore): int;

    /**
     * The user's unread notifications with result
     * notifications still behind the announcement gate
     * filtered out (bell + badge data).
     *
     * @return Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    public function visibleUnread(User $user): Collection;

    /**
     * Drop result notifications whose announcement time has
     * not come yet from a notification collection.
     *
     * @param  Collection<int, \Illuminate\Notifications\DatabaseNotification>  $notifications
     * @return Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    public function filterVisible(Collection $notifications): Collection;
}
