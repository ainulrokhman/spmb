<?php

namespace App\Services;

use App\Models\Application;
use App\Models\InvoiceInstallment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the guardian dashboard's view of an application: the
 * journey as a list of steps, each in one of a few states, so
 * the guardian can see at a glance what is done, what is theirs
 * to do next, and what is simply waiting on the committee.
 *
 * Step states:
 * - done:      finished, nothing left to do.
 * - current:   the guardian's turn; carries the action to take.
 * - attention: the guardian's turn because the committee sent
 *              something back (needs revision).
 * - waiting:   the guardian's part is done; the ball is with
 *              the committee (verification, announcement, a
 *              pending request or payment confirmation).
 * - todo:      not reached yet.
 * - stopped:   the journey ended here (rejected / not passed);
 *              steps after a stopped step are not returned.
 *
 * Pure derivation from the application, its form fee and its
 * invoice — no writes, no extra state to keep in sync.
 */
class GuardianProgressService
{
    /**
     * The journey steps for one application.
     *
     * @return list<array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}>
     */
    public function stepsFor(Application $application): array
    {
        $steps = [$this->formFeeStep($application)];

        foreach ([$this->formStep($application), $this->documentsStep($application), $this->verificationStep($application)] as $step) {
            $steps[] = $step;

            if ($step['state'] === 'stopped') {
                return $steps;
            }
        }

        $announcement = $this->announcementStep($application);
        $steps[] = $announcement;

        if ($announcement['state'] === 'stopped') {
            return $steps;
        }

        $steps[] = $this->reRegistrationStep($application);

        return $steps;
    }

    /**
     * One action item per child whose next move belongs to the
     * guardian, most urgent first: applications sent back for
     * revision, then actions with the nearest due date, then
     * the rest in dashboard order.
     *
     * @param  Collection<int, Application>  $applications
     * @return list<array{application: Application, label: string, detail: ?string, url: string, due_at: ?Carbon, attention: bool}>
     */
    public function actionsFor(Collection $applications): array
    {
        $items = [];

        foreach ($applications as $application) {
            foreach ($this->stepsFor($application) as $step) {
                if (in_array($step['state'], ['current', 'attention'], true)) {
                    $items[] = [
                        'application' => $application,
                        'label' => $step['action_label'] ?? $step['label'],
                        'detail' => $step['note'],
                        'url' => $step['action_url'] ?? route('guardian.applications.show', $application),
                        'due_at' => $step['due_at'],
                        'attention' => $step['state'] === 'attention',
                    ];

                    break;
                }
            }
        }

        return collect($items)
            ->sortBy([
                ['attention', 'desc'],
                ['due_at', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function step(string $key, string $label, string $state, ?string $note = null, ?string $actionLabel = null, ?string $actionUrl = null, ?Carbon $dueAt = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'state' => $state,
            'note' => $note,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'due_at' => $dueAt,
        ];
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function formFeeStep(Application $application): array
    {
        if ($application->feeSettled() || $application->status !== 'awaiting_form_fee') {
            return $this->step('form_fee', 'Bayar Formulir', 'done');
        }

        if ($application->form_fee_request_status === 'revision') {
            return $this->step('form_fee', 'Bayar Formulir', 'attention', 'Panitia meminta revisi pengajuan keringananmu: '.($application->form_fee_decision_note ?: 'perbaiki pengajuanmu lalu kirim ulang.'), 'Perbaiki Pengajuan', route('guardian.applications.show', $application));
        }

        if ($application->form_fee_request_status === 'pending') {
            return $this->step('form_fee', 'Bayar Formulir', 'waiting', 'Pengajuan keringananmu sedang menunggu keputusan panitia.');
        }

        return match ($application->form_fee_status) {
            'awaiting_confirmation' => $this->step('form_fee', 'Bayar Formulir', 'waiting', 'Pembayaranmu sedang menunggu konfirmasi panitia.'),
            'awaiting_payment' => $this->step('form_fee', 'Bayar Formulir', 'current', 'Pembayaran sudah dimulai — selesaikan sebelum kedaluwarsa.', 'Selesaikan Pembayaran', route('guardian.applications.show', $application)),
            default => $this->step('form_fee', 'Bayar Formulir', 'current', 'Biaya formulir '.$this->money($application->payableAmount()).' harus diselesaikan agar bisa lanjut mengisi formulir.', 'Bayar Formulir', route('guardian.applications.show', $application)),
        };
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function formStep(Application $application): array
    {
        return match (true) {
            $application->status === 'filling_form' => $this->step('form', 'Isi Formulir', 'current', 'Lengkapi biodata dan jawaban formulir.', 'Isi Formulir', route('guardian.applications.show', $application)),
            in_array($application->status, ['uploading_documents', 'submitted', 'verified', 'needs_revision', 'rejected', 'passed', 'failed', 're_registered'], true) => $this->step('form', 'Isi Formulir', 'done'),
            default => $this->step('form', 'Isi Formulir', 'todo'),
        };
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function documentsStep(Application $application): array
    {
        return match (true) {
            $application->status === 'uploading_documents' => $this->step('documents', 'Unggah Berkas', 'current', 'Unggah berkas yang disyaratkan unit tujuan.', 'Unggah Berkas', route('guardian.applications.show', $application)),
            in_array($application->status, ['submitted', 'verified', 'needs_revision', 'rejected', 'passed', 'failed', 're_registered'], true) => $this->step('documents', 'Unggah Berkas', 'done'),
            default => $this->step('documents', 'Unggah Berkas', 'todo'),
        };
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function verificationStep(Application $application): array
    {
        return match ($application->status) {
            'submitted' => $this->step('verification', 'Verifikasi Panitia', 'waiting', 'Pendaftaranmu sudah terkirim dan sedang menunggu verifikasi panitia.'),
            'needs_revision' => $this->step('verification', 'Verifikasi Panitia', 'attention', 'Perlu perbaikan: '.($application->verification_note ?: 'panitia meminta perbaikan pada pendaftaranmu.'), 'Perbaiki Pendaftaran', route('guardian.applications.show', $application)),
            'rejected' => $this->step('verification', 'Verifikasi Panitia', 'stopped', 'Pendaftaran ditolak panitia.'.($application->verification_note ? ' Catatan: '.$application->verification_note : '')),
            'verified', 'passed', 'failed', 're_registered' => $this->step('verification', 'Verifikasi Panitia', 'done'),
            default => $this->step('verification', 'Verifikasi Panitia', 'todo'),
        };
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function announcementStep(Application $application): array
    {
        if (in_array($application->status, ['passed', 'failed'], true)) {
            if (! $application->resultsAnnounced()) {
                // The outcome exists but must stay hidden until
                // the announcement time; word it neutrally.
                $at = $application->announcementAt();

                return $this->step('announcement', 'Pengumuman Hasil', 'waiting', 'Hasil diumumkan '.($at ? 'pada '.$at->format('d M Y H:i') : 'sesuai jadwal panitia').'.');
            }

            return $application->status === 'passed'
                ? $this->step('announcement', 'Pengumuman Hasil', 'done', 'Lulus! Selamat — lanjutkan ke daftar ulang.')
                : $this->step('announcement', 'Pengumuman Hasil', 'stopped', 'Tidak lulus. Terima kasih sudah mendaftar; silakan mencoba pada gelombang atau unit lain bila tersedia.');
        }

        if ($application->status === 'verified') {
            $at = $application->announcementAt();

            return $this->step('announcement', 'Pengumuman Hasil', 'waiting', 'Hasil diumumkan '.($at ? 'pada '.$at->format('d M Y H:i') : 'sesuai jadwal panitia').'.');
        }

        if ($application->status === 're_registered') {
            return $this->step('announcement', 'Pengumuman Hasil', 'done', 'Lulus! Selamat — lanjutkan ke daftar ulang.');
        }

        return $this->step('announcement', 'Pengumuman Hasil', 'todo');
    }

    /**
     * @return array{key: string, label: string, state: string, note: ?string, action_label: ?string, action_url: ?string, due_at: ?Carbon}
     */
    private function reRegistrationStep(Application $application): array
    {
        if ($application->status === 're_registered') {
            return $this->step('re_registration', 'Daftar Ulang', 'done', 'Daftar ulang selesai. Selamat bergabung!');
        }

        if ($application->status !== 'passed' || ! $application->resultsAnnounced()) {
            return $this->step('re_registration', 'Daftar Ulang', 'todo');
        }

        $invoice = $application->invoice;

        if ($invoice === null) {
            return $this->step('re_registration', 'Daftar Ulang', 'waiting', 'Tagihan daftar ulang sedang disiapkan panitia.');
        }

        if ($invoice->status === 'paid') {
            return $this->step('re_registration', 'Daftar Ulang', 'done', 'Daftar ulang selesai. Selamat bergabung!');
        }

        $url = route('guardian.invoices.show', $application);

        if ($invoice->request_status === 'revision' || $invoice->installment_request_status === 'revision') {
            $note = $invoice->request_status === 'revision' ? $invoice->decision_note : $invoice->installment_decision_note;

            return $this->step('re_registration', 'Daftar Ulang', 'attention', 'Panitia meminta revisi pengajuanmu pada tagihan ini: '.($note ?: 'perbaiki pengajuanmu lalu kirim ulang.'), 'Perbaiki Pengajuan', $url);
        }

        if ($invoice->request_status === 'pending' || $invoice->installment_request_status === 'pending') {
            return $this->step('re_registration', 'Daftar Ulang', 'waiting', 'Pengajuanmu pada tagihan ini sedang menunggu keputusan panitia.');
        }

        if ($invoice->status === 'awaiting_confirmation') {
            return $this->step('re_registration', 'Daftar Ulang', 'waiting', 'Pembayaran tagihanmu sedang menunggu konfirmasi panitia.');
        }

        $term = $invoice->installments
            ->where('status', '!=', 'paid')
            ->sortBy('sequence')
            ->first();

        if ($term instanceof InvoiceInstallment) {
            return match ($term->status) {
                'awaiting_confirmation' => $this->step('re_registration', 'Daftar Ulang', 'waiting', 'Pembayaran termin ke-'.$term->sequence.' sedang menunggu konfirmasi panitia.'),
                'awaiting_payment' => $this->step('re_registration', 'Daftar Ulang', 'current', 'Selesaikan pembayaran termin ke-'.$term->sequence.' sebesar '.$this->money($term->amount).'.', 'Selesaikan Pembayaran', $url, $term->due_date),
                default => $this->step('re_registration', 'Daftar Ulang', 'current', 'Termin ke-'.$term->sequence.' sebesar '.$this->money($term->amount).' — '.($term->due_date->isPast() ? 'sudah lewat jatuh tempo sejak ' : 'jatuh tempo ').$term->due_date->format('d M Y').'.', 'Bayar Termin', $url, $term->due_date),
            };
        }

        return $this->step('re_registration', 'Daftar Ulang', 'current', 'Sisa tagihan daftar ulang '.$this->money($invoice->remainingAmount()).' menunggu pembayaran.', 'Bayar Tagihan', $url);
    }

    private function money(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
