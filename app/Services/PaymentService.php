<?php

namespace App\Services;

use App\Contracts\ApplicationServiceInterface;
use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Services\Payments\ManualChannel;
use App\Services\Payments\MidtransChannel;
use App\Services\Payments\PaymentChannelInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Orchestrates payments across channel drivers: starting payments,
 * manual declarations and confirmations, and Midtrans webhook
 * notifications. Payables are applications (form fee) and invoices
 * (re-registration); new channels register in channels() only.
 */
class PaymentService
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
        private readonly InvoiceServiceInterface $invoices,
        private readonly InstallmentServiceInterface $installments,
    ) {}

    /**
     * All registered channel drivers, keyed by channel key. The BSI
     * custom driver will be added here once the bank grants access.
     *
     * @return array<string, PaymentChannelInterface>
     */
    public function channels(): array
    {
        return [
            'manual' => app(ManualChannel::class),
            'midtrans' => app(MidtransChannel::class),
        ];
    }

    /**
     * Channel drivers the guardian may currently choose.
     *
     * @return array<string, PaymentChannelInterface>
     */
    public function enabledChannels(): array
    {
        return array_filter($this->channels(), fn (PaymentChannelInterface $channel) => $channel->isEnabled());
    }

    public function channel(string $key): ?PaymentChannelInterface
    {
        return $this->channels()[$key] ?? null;
    }

    /**
     * Start (or resume) the form fee payment of an application on the
     * chosen channel.
     *
     * @return array{payment: Payment, view: array<string, mixed>}
     */
    public function startPayment(Application $application, string $channelKey): array
    {
        if ($application->status !== 'awaiting_form_fee' || $application->feeSettled()) {
            throw new InvalidArgumentException('Pembayaran formulir tidak sedang diperlukan.');
        }

        $result = $this->createOrResume($application, $application->payableAmount(), $channelKey, 'FEE');

        $application->update(['form_fee_status' => 'awaiting_payment']);

        return $result;
    }

    /**
     * Start (or resume) the payment of a re-registration invoice on
     * the chosen channel.
     *
     * @return array{payment: Payment, view: array<string, mixed>}
     */
    public function startInvoicePayment(Invoice $invoice, string $channelKey): array
    {
        if ($invoice->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas.');
        }

        if ($invoice->hasInstallmentPlan()) {
            throw new InvalidArgumentException('Tagihan ini dibayar per termin cicilan.');
        }

        if ($invoice->payableAmount() <= 0) {
            throw new InvalidArgumentException('Tidak ada nominal yang perlu dibayar.');
        }

        $result = $this->createOrResume($invoice, $invoice->payableAmount(), $channelKey, 'INV');

        $invoice->update(['status' => 'awaiting_payment']);

        return $result;
    }

    /**
     * Start (or resume) the payment of one installment term on the
     * chosen channel. Each term is paid in full as its own payment.
     *
     * @return array{payment: Payment, view: array<string, mixed>}
     */
    public function startInstallmentPayment(InvoiceInstallment $installment, string $channelKey): array
    {
        if ($installment->status === 'paid') {
            throw new InvalidArgumentException('Termin ini sudah lunas.');
        }

        if ($installment->invoice?->status === 'paid') {
            throw new InvalidArgumentException('Tagihan sudah lunas.');
        }

        $result = $this->createOrResume($installment, (int) $installment->amount, $channelKey, 'CIC');

        $installment->update(['status' => 'awaiting_payment']);

        return $result;
    }

    /**
     * Shared start logic: an active payment on the same channel is
     * resumed, one on another channel blocks, otherwise a new
     * payment is created and handed to the channel driver.
     *
     * @return array{payment: Payment, view: array<string, mixed>}
     */
    private function createOrResume(Model $payable, int $amount, string $channelKey, string $referencePrefix): array
    {
        $channel = $this->channel($channelKey);

        if ($channel === null || ! $channel->isEnabled()) {
            throw new InvalidArgumentException('Kanal pembayaran tidak tersedia.');
        }

        $active = $payable->payments()
            ->whereIn('status', ['pending', 'awaiting_confirmation'])
            ->latest()
            ->first();

        if ($active !== null) {
            if ($active->channel !== $channelKey) {
                throw new InvalidArgumentException('Masih ada pembayaran berjalan pada kanal '.$active->channel.'. Selesaikan atau tunggu kedaluwarsa terlebih dahulu.');
            }

            return ['payment' => $active, 'view' => $channel->createPayment($active)];
        }

        $payment = $payable->payments()->create([
            'channel' => $channelKey,
            'amount' => $amount,
            'status' => 'pending',
            'reference' => $referencePrefix.'-'.$payable->id.'-'.Str::upper(Str::random(8)),
        ]);

        return ['payment' => $payment, 'view' => $channel->createPayment($payment)];
    }

    /**
     * The guardian declares a manual payment as transferred/paid; a
     * committee member still has to confirm it.
     */
    public function declareManualPaid(Payment $payment): Payment
    {
        if ($payment->channel !== 'manual' || $payment->status !== 'pending') {
            throw new InvalidArgumentException('Pembayaran ini tidak dapat dinyatakan lunas.');
        }

        $payment->update(['status' => 'awaiting_confirmation']);

        if ($payment->payable instanceof Application) {
            $payment->payable->update(['form_fee_status' => 'awaiting_confirmation']);
        } elseif ($payment->payable instanceof Invoice) {
            $payment->payable->update(['status' => 'awaiting_confirmation']);
        } elseif ($payment->payable instanceof InvoiceInstallment) {
            $payment->payable->update(['status' => 'awaiting_confirmation']);
        }

        return $payment;
    }

    /**
     * Settle a payment (committee confirmation for manual payments,
     * or a verified channel notification) and unlock the payable's
     * next step.
     */
    public function confirmPayment(Payment $payment): Payment
    {
        if ($payment->status === 'paid') {
            return $payment;
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        if ($payment->payable instanceof Application) {
            $this->applications->settleFeeByPayment($payment->payable);
        } elseif ($payment->payable instanceof Invoice) {
            $this->invoices->markPaid($payment->payable);
        } elseif ($payment->payable instanceof InvoiceInstallment) {
            $this->installments->settleInstallment($payment->payable);
        }

        return $payment;
    }

    /**
     * Handle a Midtrans notification payload. Returns false when the
     * notification is unknown or fails signature verification.
     */
    public function handleMidtransNotification(array $payload): bool
    {
        $payment = Payment::query()->where('reference', $payload['order_id'] ?? '')->first();

        if ($payment === null || $payment->channel !== 'midtrans') {
            return false;
        }

        $channel = $this->channel('midtrans');

        if (! $channel instanceof MidtransChannel || ! $channel->signatureIsValid($payload)) {
            return false;
        }

        $status = $payload['transaction_status'] ?? '';

        if ($status === 'settlement' || ($status === 'capture' && ($payload['fraud_status'] ?? 'accept') === 'accept')) {
            $this->confirmPayment($payment);
        } elseif ($status === 'expire') {
            $payment->update(['status' => 'expired']);
            $this->resetPayable($payment);
        } elseif (in_array($status, ['cancel', 'deny'], true)) {
            $payment->update(['status' => 'failed']);
            $this->resetPayable($payment);
        }

        return true;
    }

    /**
     * An expired/failed payment frees the payable to be paid again.
     */
    private function resetPayable(Payment $payment): void
    {
        if ($payment->payable instanceof Application
            && $payment->payable->status === 'awaiting_form_fee') {
            $payment->payable->update(['form_fee_status' => 'unpaid']);
        }

        if ($payment->payable instanceof Invoice
            && $payment->payable->status !== 'paid') {
            $payment->payable->update(['status' => 'unpaid']);
        }

        if ($payment->payable instanceof InvoiceInstallment
            && $payment->payable->status !== 'paid') {
            $payment->payable->update(['status' => 'unpaid']);
        }
    }
}
