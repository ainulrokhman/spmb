<?php

namespace App\Services\Whatsapp;

use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappMessageTemplate;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatic WhatsApp notifications (WhatsApp Tahap 3): the
 * WhatsApp counterpart of the in-app guardian notifications,
 * fired from the same domain events and rendered from the
 * Tahap 2 templates. Every method is best-effort — a failed
 * or skipped send never disturbs the flow that triggered it
 * (the sender already never throws; data assembly is guarded
 * here as well).
 *
 * Messages go to the guardian account holder's phone through
 * the child's unit device chain. Result announcements are
 * deliberately NOT sent from the decision flow: they go out
 * only once the results are publicly announced, via the
 * whatsapp:notify-results sweep, so WhatsApp can never leak
 * a result earlier than the in-app announcement does.
 */
class WhatsappNotificationService
{
    public function __construct(
        private readonly WhatsappSender $sender,
    ) {}

    /**
     * A new application was started (registration or an added
     * child): the guardian hears the registration was received.
     */
    public function registrationStarted(Application $application): void
    {
        $this->send($application, WhatsappMessageLog::TYPE_REGISTRATION, [
            'unit' => $application->schoolUnit?->name ?? '',
            'gelombang' => $application->admissionWave?->name ?? '',
            'jalur' => $application->admissionTrack?->name ?? '',
        ]);
    }

    public function formFeePaid(Application $application): void
    {
        $this->send($application, WhatsappMessageLog::TYPE_PAYMENT, [
            'jenis' => 'Formulir',
            'nominal' => $this->rupiah($application->payableAmount()),
            'status' => 'Lunas',
        ]);
    }

    public function invoicePaid(Invoice $invoice): void
    {
        $application = $invoice->application;
        if ($application === null) {
            return;
        }

        $this->send($application, WhatsappMessageLog::TYPE_PAYMENT, [
            'jenis' => 'Daftar Ulang',
            'nominal' => $this->rupiah($invoice->payableAmount()),
            'status' => 'Lunas',
        ]);
    }

    public function installmentPaid(InvoiceInstallment $installment): void
    {
        $application = $installment->invoice?->application;
        if ($application === null) {
            return;
        }

        $this->send($application, WhatsappMessageLog::TYPE_PAYMENT, [
            'jenis' => 'Termin '.$installment->sequence.' Daftar Ulang',
            'nominal' => $this->rupiah((int) $installment->amount),
            'status' => 'Lunas',
        ]);
    }

    public function formReliefDecided(Application $application, string $outcome, bool $directGrant = false): void
    {
        $this->send($application, WhatsappMessageLog::TYPE_DECISION, [
            'jenis_pengajuan' => 'keringanan biaya formulir',
            'keputusan' => $this->outcomeLabel($outcome, $directGrant),
            'keterangan' => $this->decisionNote(
                $application->form_fee_decision_note,
                $outcome === 'revision' ? null : 'Sisa yang perlu dibayar: '.$this->rupiah($application->payableAmount()).'.',
            ),
        ]);
    }

    public function invoiceReliefDecided(Invoice $invoice, string $outcome, bool $directGrant = false): void
    {
        $application = $invoice->application;
        if ($application === null) {
            return;
        }

        $this->send($application, WhatsappMessageLog::TYPE_DECISION, [
            'jenis_pengajuan' => 'keringanan tagihan daftar ulang',
            'keputusan' => $this->outcomeLabel($outcome, $directGrant),
            'keterangan' => $this->decisionNote(
                $invoice->decision_note,
                $outcome === 'revision' ? null : 'Sisa tagihan: '.$this->rupiah($invoice->payableAmount()).'.',
            ),
        ]);
    }

    public function installmentDecided(Invoice $invoice, string $outcome): void
    {
        $application = $invoice->application;
        if ($application === null) {
            return;
        }

        $keterangan = $outcome === 'approved'
            ? 'Tagihan dapat dibayar dalam '.$invoice->installments()->count().' termin. Lihat rincian dan jadwalnya di aplikasi SPMB.'
            : $this->decisionNote($invoice->installment_decision_note, null);

        $this->send($application, WhatsappMessageLog::TYPE_DECISION, [
            'jenis_pengajuan' => 'cicilan tagihan daftar ulang',
            'keputusan' => $this->outcomeLabel($outcome, false),
            'keterangan' => $keterangan,
        ]);
    }

    /**
     * Due-date reminder for one installment term (H-3 and
     * due-day, from the daily reminder command). Guarded by
     * the send log: the same rendered reminder is never sent
     * twice to the same guardian on the same day, so re-running
     * the command is safe.
     */
    public function installmentDue(InvoiceInstallment $installment): void
    {
        $application = $installment->invoice?->application;
        if ($application === null) {
            return;
        }

        $this->sendGuarded($application, WhatsappMessageLog::TYPE_REMINDER, [
            'jenis' => 'Termin '.$installment->sequence.' Daftar Ulang',
            'nominal' => $this->rupiah((int) $installment->amount),
            'jatuh_tempo' => $installment->due_date?->format('d M Y') ?? '',
        ], sameDayOnly: true);
    }

    /**
     * The selection result, sent by the announcement sweep
     * once resultsAnnounced() is true. Guarded by the send
     * log so each result is announced exactly once.
     */
    public function resultAnnounced(Application $application): void
    {
        $passed = $application->status === 'passed';

        $this->sendGuarded($application, WhatsappMessageLog::TYPE_RESULT, [
            'unit' => $application->schoolUnit?->name ?? '',
            'hasil' => $passed ? 'Lulus' : 'Tidak Lulus',
            'keterangan' => $passed
                ? 'Silakan melanjutkan ke tahap daftar ulang untuk resmi menjadi siswa baru.'
                : 'Terima kasih sudah mendaftar pada gelombang ini.',
        ], sameDayOnly: false);
    }

    /**
     * Render and send one templated message to the guardian
     * account holder, adding the shared name variables.
     *
     * @param  array<string, mixed>  $data
     */
    private function send(Application $application, string $type, array $data): void
    {
        try {
            $guardian = $application->user;
            if ($guardian === null || blank($guardian->phone)) {
                return;
            }

            $this->sender->sendTemplated(
                $application->school_unit_id,
                $guardian->phone,
                $type,
                array_merge($this->baseData($application), $data),
                $guardian->name,
            );
        } catch (Throwable $e) {
            $this->logFailure($application, $type, $e);
        }
    }

    /**
     * Like send(), but first renders the message and checks
     * the send log so the identical message is not delivered
     * twice (same day only, or ever, per $sameDayOnly).
     *
     * @param  array<string, mixed>  $data
     */
    private function sendGuarded(Application $application, string $type, array $data, bool $sameDayOnly): void
    {
        try {
            $guardian = $application->user;
            if ($guardian === null || blank($guardian->phone)) {
                return;
            }

            $template = WhatsappMessageTemplate::activeFor($type);
            if ($template === null) {
                // Inactive template: let the sender log the skip.
                $this->send($application, $type, $data);

                return;
            }

            $message = $template->render(array_merge($this->baseData($application), $data));
            $target = PhoneNumber::toWhatsApp($guardian->phone);
            if ($target === null) {
                return;
            }

            $already = WhatsappMessageLog::query()
                ->where('type', $type)
                ->where('recipient_phone', $target)
                ->where('status', WhatsappMessageLog::STATUS_SENT)
                ->where('message', $message)
                ->when($sameDayOnly, fn ($query) => $query->whereDate('created_at', now()->toDateString()))
                ->exists();

            if ($already) {
                return;
            }

            $this->sender->send($application->school_unit_id, $guardian->phone, $message, $type, $guardian->name);
        } catch (Throwable $e) {
            $this->logFailure($application, $type, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function baseData(Application $application): array
    {
        return [
            'nama_wali' => $application->user?->name ?? '',
            'nama_anak' => $application->child_name ?? 'Anak Anda',
        ];
    }

    private function outcomeLabel(string $outcome, bool $directGrant): string
    {
        if ($directGrant) {
            return 'Diberikan Keringanan';
        }

        return match ($outcome) {
            'approved' => 'Disetujui',
            'revision' => 'Perlu Revisi',
            default => 'Ditolak',
        };
    }

    private function decisionNote(?string $note, ?string $payableLine): string
    {
        $parts = array_filter([
            filled($note) ? 'Catatan panitia: '.$note : null,
            $payableLine,
        ]);

        return implode(' ', $parts);
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function logFailure(Application $application, string $type, Throwable $e): void
    {
        Log::warning('Notifikasi WhatsApp otomatis gagal disiapkan.', [
            'application_id' => $application->id,
            'type' => $type,
            'error' => $e->getMessage(),
        ]);
    }
}
