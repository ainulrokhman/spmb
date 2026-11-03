<?php

namespace App\Services;

use App\Contracts\GuardianNotificationServiceInterface;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GuardianNotification;
use App\Notifications\GuardianInstallmentDueNotification;
use App\Notifications\GuardianInstallmentNotification;
use App\Notifications\GuardianPaymentNotification;
use App\Notifications\GuardianReliefNotification;
use App\Notifications\GuardianResultNotification;
use App\Notifications\GuardianVerificationNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Guardian (wali) notifications, in-app only (database
 * channel, same table as the officer notifications). Every
 * message names the child and links to the child's page.
 * Result notifications are written when the committee
 * decides but stay invisible until the announcement time:
 * the bell, the list, and the open-action all consult
 * filterVisible(), which checks each result's application
 * live.
 */
class GuardianNotificationService implements GuardianNotificationServiceInterface
{
    public function notifyFormReliefDecided(Application $application, bool $approved, bool $directGrant = false): void
    {
        $child = $application->child_name ?? 'Anak Anda';
        $payable = 'Sisa yang perlu dibayar: Rp '.number_format($application->payableAmount(), 0, ',', '.').'.';

        if ($directGrant) {
            $title = 'Anda Mendapat Keringanan Biaya Formulir';
            $message = 'Panitia memberikan keringanan biaya formulir untuk '.$child.': '
                .$this->describeRelief($application->form_fee_discount_type, $application->form_fee_discount_value).'. '.$payable;
        } elseif ($approved) {
            $title = 'Keringanan Biaya Formulir Disetujui';
            $message = 'Pengajuan keringanan biaya formulir untuk '.$child.' disetujui: '
                .$this->describeRelief($application->form_fee_discount_type, $application->form_fee_discount_value).'. '.$payable;
        } else {
            $title = 'Pengajuan Keringanan Formulir Ditolak';
            $message = 'Pengajuan keringanan biaya formulir untuk '.$child.' belum dapat disetujui.'
                .$this->noteSuffix($application->form_fee_decision_note).' '.$payable;
        }

        $this->send($application, new GuardianReliefNotification(
            'g_form_relief:'.$application->id,
            $title,
            $message,
            route('guardian.applications.detail', $application),
            $application->id,
        ));
    }

    public function notifyInvoiceReliefDecided(Invoice $invoice, bool $approved, bool $directGrant = false): void
    {
        $application = $invoice->application;

        if ($application === null) {
            return;
        }

        $child = $application->child_name ?? 'Anak Anda';
        $payable = 'Sisa tagihan: Rp '.number_format($invoice->payableAmount(), 0, ',', '.').'.';

        if ($directGrant) {
            $title = 'Anda Mendapat Keringanan Tagihan';
            $message = 'Panitia memberikan keringanan tagihan daftar ulang untuk '.$child.': '
                .$this->describeRelief($invoice->discount_type, $invoice->discount_value).'. '.$payable;
        } elseif ($approved) {
            $title = 'Keringanan Tagihan Disetujui';
            $message = 'Pengajuan keringanan tagihan daftar ulang untuk '.$child.' disetujui: '
                .$this->describeRelief($invoice->discount_type, $invoice->discount_value).'. '.$payable;
        } else {
            $title = 'Pengajuan Keringanan Tagihan Ditolak';
            $message = 'Pengajuan keringanan tagihan daftar ulang untuk '.$child.' belum dapat disetujui.'
                .$this->noteSuffix($invoice->decision_note).' '.$payable;
        }

        $this->send($application, new GuardianReliefNotification(
            'g_invoice_relief:'.$invoice->id,
            $title,
            $message,
            route('guardian.invoices.show', $application),
            $application->id,
        ));
    }

    public function notifyInstallmentDecided(Invoice $invoice, bool $approved): void
    {
        $application = $invoice->application;

        if ($application === null) {
            return;
        }

        $child = $application->child_name ?? 'Anak Anda';

        if ($approved) {
            $title = 'Pengajuan Cicilan Disetujui';
            $message = 'Tagihan daftar ulang '.$child.' dapat dibayar dalam '
                .$invoice->installments()->count().' termin. Lihat rincian dan jadwalnya di halaman daftar ulang.';
        } else {
            $title = 'Pengajuan Cicilan Ditolak';
            $message = 'Pengajuan cicilan tagihan daftar ulang untuk '.$child.' belum dapat disetujui.'
                .$this->noteSuffix($invoice->installment_decision_note);
        }

        $this->send($application, new GuardianInstallmentNotification(
            'g_installment:'.$invoice->id,
            $title,
            $message,
            route('guardian.invoices.show', $application),
            $application->id,
        ));
    }

    public function notifyVerificationDecided(Application $application): void
    {
        $child = $application->child_name ?? 'Anak Anda';

        [$title, $message] = match ($application->status) {
            'verified' => [
                'Pendaftaran Terverifikasi',
                'Berkas pendaftaran '.$child.' sudah terverifikasi. Hasil kelulusan akan diumumkan sesuai jadwal pengumuman.',
            ],
            'needs_revision' => [
                'Pendaftaran Perlu Perbaikan',
                'Pendaftaran '.$child.' perlu diperbaiki.'.$this->noteSuffix($application->verification_note).' Silakan perbaiki melalui halaman pendaftaran.',
            ],
            'rejected' => [
                'Pendaftaran Ditolak',
                'Mohon maaf, pendaftaran '.$child.' ditolak oleh panitia.'.$this->noteSuffix($application->verification_note),
            ],
            default => [null, null],
        };

        if ($title === null) {
            return;
        }

        $this->send($application, new GuardianVerificationNotification(
            'g_verification:'.$application->id,
            $title,
            $message,
            route('guardian.applications.detail', $application),
            $application->id,
        ));
    }

    public function notifyResultDecided(Application $application): void
    {
        $child = $application->child_name ?? 'Anak Anda';
        $passed = $application->status === 'passed';

        $this->send($application, new GuardianResultNotification(
            'g_result:'.$application->id,
            $passed ? 'Hasil Pengumuman: Lulus' : 'Hasil Pengumuman: Tidak Lulus',
            $passed
                ? 'Selamat! '.$child.' dinyatakan LULUS seleksi. Silakan selesaikan daftar ulang untuk resmi menjadi siswa baru.'
                : 'Mohon maaf, '.$child.' dinyatakan TIDAK LULUS seleksi pada gelombang ini. Terima kasih sudah mendaftar.',
            route('guardian.applications.detail', $application),
            $application->id,
            'result',
        ));
    }

    public function notifyFormFeePaid(Application $application): void
    {
        $child = $application->child_name ?? 'Anak Anda';

        $this->send($application, new GuardianPaymentNotification(
            'g_form_paid:'.$application->id,
            'Pembayaran Formulir Terkonfirmasi',
            'Pembayaran biaya formulir '.$child.' sebesar Rp '.number_format($application->payableAmount(), 0, ',', '.')
                .' sudah terkonfirmasi. Silakan lanjutkan mengisi formulir pendaftaran.',
            route('guardian.applications.show', $application),
            $application->id,
        ));
    }

    public function notifyInvoicePaid(Invoice $invoice): void
    {
        $application = $invoice->application;

        if ($application === null) {
            return;
        }

        $child = $application->child_name ?? 'Anak Anda';

        $this->send($application, new GuardianPaymentNotification(
            'g_invoice_paid:'.$invoice->id,
            'Daftar Ulang Selesai',
            'Tagihan daftar ulang '.$child.' sudah lunas. '.$child.' resmi terdaftar sebagai siswa baru. Selamat!',
            route('guardian.invoices.show', $application),
            $application->id,
        ));
    }

    public function notifyInstallmentPaid(InvoiceInstallment $installment): void
    {
        $invoice = $installment->invoice;
        $application = $invoice?->application;

        if ($invoice === null || $application === null) {
            return;
        }

        $child = $application->child_name ?? 'Anak Anda';
        $remaining = $invoice->payableAmount()
            - (int) $invoice->installments()->where('status', 'paid')->sum('amount');

        $this->send($application, new GuardianPaymentNotification(
            'g_installment_paid:'.$installment->id,
            'Termin '.$installment->sequence.' Lunas',
            'Pembayaran termin '.$installment->sequence.' tagihan daftar ulang '.$child.' sudah terkonfirmasi.'
                .($remaining > 0 ? ' Sisa tagihan: Rp '.number_format($remaining, 0, ',', '.').'.' : ''),
            route('guardian.invoices.show', $application),
            $application->id,
        ));
    }

    public function notifyPaymentUnsuccessful(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Application) {
            $application = $payable;
            $subject = 'biaya formulir';
            $url = route('guardian.applications.show', $application);
        } elseif ($payable instanceof Invoice) {
            $application = $payable->application;
            $subject = 'tagihan daftar ulang';
            $url = $application !== null ? route('guardian.invoices.show', $application) : route('guardian.dashboard');
        } elseif ($payable instanceof InvoiceInstallment) {
            $application = $payable->invoice?->application;
            $subject = 'termin '.$payable->sequence.' tagihan daftar ulang';
            $url = $application !== null ? route('guardian.invoices.show', $application) : route('guardian.dashboard');
        } else {
            return;
        }

        if ($application === null) {
            return;
        }

        $child = $application->child_name ?? 'Anak Anda';
        $state = $payment->status === 'expired' ? 'kedaluwarsa' : 'gagal';

        $this->send($application, new GuardianPaymentNotification(
            'g_payment_unsuccessful:'.$payment->id,
            'Pembayaran Belum Berhasil',
            'Pembayaran '.$subject.' '.$child.' '.$state.'. Silakan lakukan pembayaran kembali.',
            $url,
            $application->id,
        ));
    }

    public function notifyInstallmentDue(InvoiceInstallment $installment, int $daysBefore): int
    {
        $invoice = $installment->invoice;
        $application = $invoice?->application;

        if ($invoice === null || $application === null) {
            return 0;
        }

        $child = $application->child_name ?? 'Anak Anda';
        $eventKey = 'installment_due:'.$installment->id.':h'.$daysBefore;
        $dueText = $installment->due_date?->format('d M Y');

        $notification = new GuardianInstallmentDueNotification(
            $eventKey,
            'Pengingat Jatuh Tempo Termin '.$installment->sequence,
            'Termin '.$installment->sequence.' tagihan daftar ulang '.$child.' sebesar Rp '
                .number_format($installment->amount, 0, ',', '.').' jatuh tempo '
                .($daysBefore === 0 ? 'HARI INI ('.$dueText.').' : 'pada '.$dueText.' ('.$daysBefore.' hari lagi).'),
            route('guardian.invoices.show', $application),
            $application->id,
        );

        $sent = 0;

        foreach ($this->recipients($application) as $user) {
            $already = DB::table('notifications')
                ->where('notifiable_type', $user::class)
                ->where('notifiable_id', $user->id)
                ->where('data->event_key', $eventKey)
                ->exists();

            if ($already) {
                continue;
            }

            $user->notify($notification);
            $sent++;
        }

        return $sent;
    }

    public function visibleUnread(User $user): Collection
    {
        return $this->filterVisible($user->unreadNotifications);
    }

    public function filterVisible(Collection $notifications): Collection
    {
        $resultAppIds = $notifications
            ->filter(fn ($notification) => ($notification->data['kind'] ?? null) === 'result')
            ->map(fn ($notification) => $notification->data['application_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($resultAppIds->isEmpty()) {
            return $notifications;
        }

        $announcedIds = Application::query()
            ->whereIn('id', $resultAppIds)
            ->get()
            ->filter(fn (Application $application) => $application->resultsAnnounced())
            ->map(fn (Application $application) => $application->id)
            ->all();

        return $notifications
            ->reject(fn ($notification) => ($notification->data['kind'] ?? null) === 'result'
                && ! in_array($notification->data['application_id'] ?? null, $announcedIds, true))
            ->values();
    }

    /**
     * Send one notification to the account holder and every
     * linked parent account of the application.
     */
    private function send(Application $application, GuardianNotification $notification): void
    {
        $recipients = $this->recipients($application);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Application $application): Collection
    {
        $application->loadMissing('linkedUsers');

        return collect([$application->user])
            ->filter()
            ->merge($application->linkedUsers)
            ->unique('id')
            ->values();
    }

    /**
     * Plain-language description of a committee-chosen
     * relief ('waiver' / percent / nominal).
     */
    private function describeRelief(?string $type, ?int $value): string
    {
        return match ($type) {
            'waiver' => 'dibebaskan seluruhnya',
            'percent' => $value === 100
                ? 'dibebaskan seluruhnya'
                : 'diskon '.$value.'%',
            'nominal' => 'potongan Rp '.number_format((int) $value, 0, ',', '.'),
            default => 'diberikan keringanan',
        };
    }

    /**
     * Append a committee note to a message, when present.
     */
    private function noteSuffix(?string $note): string
    {
        return filled($note) ? ' Catatan panitia: '.$note : '';
    }
}
