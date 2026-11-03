<?php

namespace App\Services;

use App\Contracts\OfficerNotificationServiceInterface;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\FormFeeReliefRequestedNotification;
use App\Notifications\InstallmentRequestedNotification;
use App\Notifications\InvoiceReliefRequestedNotification;
use App\Notifications\ManualPaymentAwaitingNotification;
use App\Support\SystemAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Officer (petugas) notifications, in-app only (database
 * channel): who gets notified follows the deciding
 * permission for the matter plus the officer's unit
 * assignments; global roles (Super Admin / Admin) are always
 * included. A decision marks every officer's copy of the
 * event read, so bells do not stay noisy over handled work.
 */
class OfficerNotificationService implements OfficerNotificationServiceInterface
{
    public function notifyFormFeeReliefRequested(Application $application): void
    {
        $application->loadMissing('schoolUnit');

        Notification::send(
            $this->officersFor($application->school_unit_id, 'form-payment-decide'),
            new FormFeeReliefRequestedNotification(
                $this->formReliefKey($application),
                'Pengajuan Keringanan Formulir',
                ($application->child_name ?? 'Pendaftar').' ('.($application->schoolUnit?->name ?? '-').') mengajukan keringanan biaya formulir.',
                route('admin.form-payments.index'),
            ),
        );
    }

    public function notifyInvoiceReliefRequested(Invoice $invoice): void
    {
        $invoice->loadMissing('application.schoolUnit');
        $application = $invoice->application;

        Notification::send(
            $this->officersFor($application?->school_unit_id, 'invoice-manage'),
            new InvoiceReliefRequestedNotification(
                $this->invoiceReliefKey($invoice),
                'Pengajuan Keringanan Tagihan',
                ($application?->child_name ?? 'Pendaftar').' ('.($application?->schoolUnit?->name ?? '-').') mengajukan keringanan tagihan daftar ulang.',
                route('admin.invoices.show', $invoice),
            ),
        );
    }

    public function notifyInstallmentRequested(Invoice $invoice): void
    {
        $invoice->loadMissing('application.schoolUnit');
        $application = $invoice->application;

        Notification::send(
            $this->officersFor($application?->school_unit_id, 'invoice-manage'),
            new InstallmentRequestedNotification(
                $this->installmentKey($invoice),
                'Pengajuan Cicilan',
                ($application?->child_name ?? 'Pendaftar').' ('.($application?->schoolUnit?->name ?? '-').') mengajukan cicilan tagihan daftar ulang.',
                route('admin.invoices.show', $invoice),
            ),
        );
    }

    public function notifyManualPaymentDeclared(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Application) {
            $unitId = $payable->school_unit_id;
            $permission = 'form-payment-decide';
            $subject = 'biaya formulir '.($payable->child_name ?? '');
            $url = route('admin.form-payments.index');
        } elseif ($payable instanceof Invoice) {
            $unitId = $payable->application?->school_unit_id;
            $permission = 'invoice-manage';
            $subject = 'tagihan daftar ulang '.($payable->application?->child_name ?? '');
            $url = route('admin.invoices.show', $payable);
        } elseif ($payable instanceof InvoiceInstallment) {
            $invoice = $payable->invoice;
            $unitId = $invoice?->application?->school_unit_id;
            $permission = 'invoice-manage';
            $subject = 'termin '.$payable->sequence.' tagihan daftar ulang '.($invoice?->application?->child_name ?? '');
            $url = $invoice !== null ? route('admin.invoices.show', $invoice) : route('admin.invoices.index');
        } else {
            return;
        }

        Notification::send(
            $this->officersFor($unitId, $permission),
            new ManualPaymentAwaitingNotification(
                $this->manualPaymentKey($payment),
                'Pembayaran Manual Menunggu Konfirmasi',
                'Pembayaran manual '.$subject.' sebesar Rp '.number_format($payment->amount, 0, ',', '.').' menunggu konfirmasi.',
                $url,
            ),
        );
    }

    public function markFormFeeReliefHandled(Application $application): void
    {
        $this->markEventHandled($this->formReliefKey($application));
    }

    public function markInvoiceReliefHandled(Invoice $invoice): void
    {
        $this->markEventHandled($this->invoiceReliefKey($invoice));
    }

    public function markInstallmentHandled(Invoice $invoice): void
    {
        $this->markEventHandled($this->installmentKey($invoice));
    }

    public function markManualPaymentHandled(Payment $payment): void
    {
        $this->markEventHandled($this->manualPaymentKey($payment));
    }

    /**
     * The officers who may decide a matter: holders of the
     * deciding permission plus every global role, restricted
     * to officers assigned to the unit (globals exempt).
     *
     * @return Collection<int, User>
     */
    private function officersFor(?int $unitId, string $permission): Collection
    {
        return User::permission($permission)->get()
            ->merge(User::role([SystemAccess::SUPER_ADMIN_ROLE, SystemAccess::ADMIN_ROLE])->get())
            ->unique('id')
            ->filter(fn (User $user) => $user->hasGlobalUnitAccess()
                || ($unitId !== null && $user->hasUnitAccess($unitId)))
            ->values();
    }

    /**
     * Mark every unread copy of an event as read.
     */
    private function markEventHandled(string $eventKey): void
    {
        DB::table('notifications')
            ->where('data->event_key', $eventKey)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function formReliefKey(Application $application): string
    {
        return 'form_relief:'.$application->id;
    }

    private function invoiceReliefKey(Invoice $invoice): string
    {
        return 'invoice_relief:'.$invoice->id;
    }

    private function installmentKey(Invoice $invoice): string
    {
        return 'installment:'.$invoice->id;
    }

    private function manualPaymentKey(Payment $payment): string
    {
        return 'manual_payment:'.$payment->id;
    }
}
