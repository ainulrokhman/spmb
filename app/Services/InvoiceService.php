<?php

namespace App\Services;

use App\Contracts\FeeServiceInterface;
use App\Contracts\GuardianNotificationServiceInterface;
use App\Contracts\OfficerNotificationServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\Application;
use App\Models\Fee;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private readonly FeeServiceInterface $fees,
        private readonly OfficerNotificationServiceInterface $officerNotifications,
        private readonly GuardianNotificationServiceInterface $guardianNotifications,
    ) {}

    /**
     * The resolved draft lines for an application's re-registration
     * invoice: per active fee of the unit (a disabled mapping is
     * never billed), its mode and — for fixed fees —
     * the resolved amount, for pilihan fees the choosable tiers, and
     * for bebas fees the minimum.
     *
     * @return list<array<string, mixed>>
     */
    public function draftLines(Application $application): array
    {
        $fees = Fee::query()
            ->with('feeComponent')
            ->where('school_unit_id', $application->school_unit_id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Fee $fee) => $fee->feeComponent?->code)
            ->values();

        $lines = [];

        foreach ($fees as $fee) {
            $line = [
                'fee' => $fee,
                'mode' => $fee->mode,
                'fixed_amount' => null,
                'options' => [],
                'min_amount' => $fee->min_amount,
            ];

            if ($fee->mode === Fee::MODE_FIXED) {
                $line['fixed_amount'] = $this->fees->resolveFixedAmount($fee, $application->admission_track_id, $application->gender, $application->effectiveChildStatusId()) ?? 0;
            } elseif ($fee->mode === Fee::MODE_PILIHAN) {
                $line['options'] = $this->fees->resolveAmounts($fee, $application->admission_track_id, $application->gender, $application->effectiveChildStatusId())
                    ->pluck('amount')
                    ->map(fn ($amount) => (int) $amount)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Freeze the invoice from the guardian's selections (pilihan
     * tier choices and bebas amounts). One invoice per application.
     *
     * @param  array<int, array<string, mixed>>  $selections  keyed by fee id
     */
    public function createInvoice(Application $application, array $selections): Invoice
    {
        if ($application->status !== 'passed') {
            throw new InvalidArgumentException('Daftar ulang hanya untuk pendaftar yang dinyatakan lulus.');
        }

        if (! $application->resultsAnnounced()) {
            throw new InvalidArgumentException('Hasil pendaftaran belum diumumkan.');
        }

        $existing = $application->invoice()->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = $this->draftLines($application);

        return DB::transaction(function () use ($application, $lines, $selections) {
            $invoice = Invoice::create([
                'application_id' => $application->id,
                'total' => 0,
                'status' => 'unpaid',
            ]);

            $total = 0;

            foreach ($lines as $line) {
                /** @var Fee $fee */
                $fee = $line['fee'];
                $selection = $selections[$fee->id] ?? [];

                $amount = match ($fee->mode) {
                    Fee::MODE_FIXED => $line['fixed_amount'],
                    Fee::MODE_PILIHAN => $this->chosenTier($line, $selection),
                    Fee::MODE_BEBAS => $this->chosenFreeAmount($line, $selection),
                    default => 0,
                };

                $invoice->items()->create([
                    'fee_id' => $fee->id,
                    'component_code' => $fee->feeComponent?->code ?? '',
                    'component_name' => $fee->feeComponent?->name ?? '',
                    'mode' => $fee->mode,
                    'amount' => $amount,
                ]);

                $total += $amount;
            }

            $invoice->update(['total' => $total]);

            // A zero-total invoice needs no payment at all.
            if ($total === 0) {
                $this->markPaid($invoice);
            }

            return $invoice;
        });
    }

    /**
     * Record a guardian's relief request on the invoice. The
     * guardian only states a reason; the committee decides the
     * form and size of the relief when deciding the request.
     */
    public function requestDiscount(Invoice $invoice, string $reason, ?UploadedFile $attachment = null): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas.');
        }

        if ($invoice->request_status === 'pending') {
            throw new InvalidArgumentException('Masih ada pengajuan yang menunggu keputusan panitia.');
        }

        if (in_array($invoice->installment_request_status, ['pending', 'revision'], true)) {
            throw new InvalidArgumentException('Masih ada pengajuan cicilan yang belum selesai.');
        }

        if ($invoice->installments()->exists()) {
            throw new InvalidArgumentException('Tagihan ini sudah memiliki rincian cicilan.');
        }

        // The attachment belongs to this request: uploading a
        // new file replaces the previous one, while submitting
        // without a file keeps the existing attachment — a
        // guardian resubmitting after a revision decision often
        // only corrects the reason and must not lose the proof
        // they already uploaded.
        $filePath = $invoice->request_file_path;

        if ($attachment !== null) {
            if ($filePath) {
                Storage::disk('local')->delete($filePath);
            }

            $filePath = $attachment->store('lampiran-keringanan', 'local');
        }

        $invoice->update([
            'request_kind' => null,
            'request_type' => null,
            'request_value' => null,
            'request_reason' => $reason,
            'request_file_path' => $filePath,
            'request_status' => 'pending',
            'decision_note' => null,
        ]);

        $this->officerNotifications->notifyInvoiceReliefRequested($invoice);

        return $invoice;
    }

    /**
     * Decide a pending relief request (committee side):
     * 'approve' (the committee itself chooses the relief:
     * 'waiver' for a full waiver, or 'percent' / 'nominal' with
     * a value), 'revision' (the request goes back to the
     * guardian to fix and resubmit — a note saying what to fix
     * is required) or 'reject' (final for this request; the
     * guardian may file a new one).
     */
    public function decideDiscount(Invoice $invoice, string $decision, ?string $reliefType, ?int $reliefValue, User $decider, ?string $note): Invoice
    {
        if ($invoice->request_status !== 'pending') {
            throw new InvalidArgumentException('Tidak ada pengajuan yang menunggu keputusan.');
        }

        if (! in_array($decision, ['approve', 'revision', 'reject'], true)) {
            throw new InvalidArgumentException('Keputusan tidak dikenal.');
        }

        if ($decision === 'revision' && ($note === null || trim($note) === '')) {
            throw new InvalidArgumentException('Catatan wajib diisi bila meminta revisi, agar wali tahu apa yang harus diperbaiki.');
        }

        $invoice->request_status = match ($decision) {
            'approve' => 'approved',
            'revision' => 'revision',
            default => 'rejected',
        };
        $invoice->decision_note = $note;
        $invoice->decided_by = $decider->id;
        $invoice->decided_at = now();

        if ($decision === 'approve') {
            $this->applyRelief($invoice, ...$this->validatedRelief($reliefType, $reliefValue));

            if ($invoice->payableAmount() === 0) {
                $invoice->save();

                $this->officerNotifications->markInvoiceReliefHandled($invoice);
                $this->guardianNotifications->notifyInvoiceReliefDecided($invoice, $invoice->request_status);

                return $this->markPaid($invoice);
            }

            $invoice->status = 'unpaid';
        }

        $invoice->save();

        $this->officerNotifications->markInvoiceReliefHandled($invoice);
        $this->guardianNotifications->notifyInvoiceReliefDecided($invoice, $invoice->request_status);

        return $invoice;
    }

    /**
     * Grant invoice relief directly, without a guardian request
     * (committee initiative).
     */
    public function grantDiscount(Invoice $invoice, string $reliefType, ?int $reliefValue, User $decider, ?string $note): Invoice
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas.');
        }

        if (in_array($invoice->request_status, ['pending', 'revision'], true) || in_array($invoice->installment_request_status, ['pending', 'revision'], true)) {
            throw new InvalidArgumentException('Ada pengajuan yang belum selesai; putuskan atau selesaikan pengajuan tersebut terlebih dahulu.');
        }

        if ($invoice->discount_type !== null) {
            throw new InvalidArgumentException('Keringanan sudah pernah diberikan untuk tagihan ini.');
        }

        if ($invoice->installments()->exists()) {
            throw new InvalidArgumentException('Tagihan ini sudah memiliki rincian cicilan; keringanan tidak dapat diberikan lagi.');
        }

        $invoice->decision_note = $note;
        $invoice->decided_by = $decider->id;
        $invoice->decided_at = now();

        $this->applyRelief($invoice, ...$this->validatedRelief($reliefType, $reliefValue));

        if ($invoice->payableAmount() === 0) {
            $invoice->save();

            $this->guardianNotifications->notifyInvoiceReliefDecided($invoice, 'approved', directGrant: true);

            return $this->markPaid($invoice);
        }

        $invoice->status = 'unpaid';
        $invoice->save();

        $this->guardianNotifications->notifyInvoiceReliefDecided($invoice, 'approved', directGrant: true);

        return $invoice;
    }

    /**
     * Validate a committee-chosen relief and return it as
     * [type, value]; 'waiver' means a full (100%) relief.
     *
     * @return array{0: string, 1: int|null}
     */
    private function validatedRelief(?string $type, ?int $value): array
    {
        if ($type === 'waiver') {
            return ['waiver', null];
        }

        if ($type === 'percent' && $value !== null && $value >= 1 && $value <= 100) {
            return ['percent', $value];
        }

        if ($type === 'nominal' && $value !== null && $value >= 1) {
            return ['nominal', $value];
        }

        throw new InvalidArgumentException('Pilih bentuk keringanan yang valid: pembebasan penuh, diskon persen (1-100), atau diskon nominal.');
    }

    /**
     * Apply a validated relief to the invoice's discount fields
     * (a full waiver is stored as a 100% percent discount).
     */
    private function applyRelief(Invoice $invoice, string $type, ?int $value): void
    {
        if ($type === 'waiver') {
            $invoice->discount_type = 'percent';
            $invoice->discount_value = 100;

            return;
        }

        $invoice->discount_type = $type;
        $invoice->discount_value = $value;
    }

    /**
     * Settle the invoice and mark the application as re-registered.
     */
    public function markPaid(Invoice $invoice): Invoice
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);

        $application = $invoice->application;

        if ($application !== null && $application->status === 'passed') {
            $application->update(['status' => 're_registered']);
        }

        $this->guardianNotifications->notifyInvoicePaid($invoice);

        return $invoice;
    }

    /**
     * Validate and return the tier a guardian picked for a pilihan
     * fee.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $selection
     */
    private function chosenTier(array $line, array $selection): int
    {
        $amount = isset($selection['choice']) ? (int) $selection['choice'] : null;

        if ($amount === null || ! in_array($amount, $line['options'], true)) {
            throw new InvalidArgumentException('Pilih salah satu nominal untuk komponen '.$line['fee']->feeComponent?->name.'.');
        }

        return $amount;
    }

    /**
     * Validate and return the amount a guardian filled for a bebas
     * fee (never below the configured minimum).
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $selection
     */
    private function chosenFreeAmount(array $line, array $selection): int
    {
        $amount = isset($selection['free']) ? (int) $selection['free'] : 0;
        $minimum = $line['min_amount'] ?? 0;

        if ($amount < $minimum) {
            throw new InvalidArgumentException('Nominal '.$line['fee']->feeComponent?->name.' tidak boleh di bawah minimum Rp '.number_format($minimum, 0, ',', '.').'.');
        }

        return $amount;
    }
}
