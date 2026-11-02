<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Midtrans channel: creates a Snap transaction; the payment outcome
 * arrives through the notification webhook whose signature is
 * verified before any state changes.
 */
class MidtransChannel implements PaymentChannelInterface
{
    public function key(): string
    {
        return 'midtrans';
    }

    public function label(): string
    {
        return 'Midtrans (VA / E-Wallet / QRIS)';
    }

    public function isEnabled(): bool
    {
        return Setting::flag('payment_midtrans_enabled', false)
            && ! empty(config('midtrans.server_key'))
            && ! empty(config('midtrans.client_key'));
    }

    public function createPayment(Payment $payment): array
    {
        $payable = $payment->payable;
        $guardian = match (true) {
            $payable instanceof \App\Models\Invoice => $payable->application?->user,
            $payable instanceof \App\Models\InvoiceInstallment => $payable->invoice?->application?->user,
            default => $payable->user,
        };

        [$itemId, $itemName] = match (true) {
            $payable instanceof \App\Models\Invoice => ['TAGIHAN-'.$payable->id, 'Tagihan Daftar Ulang SPMB'],
            $payable instanceof \App\Models\InvoiceInstallment => ['CICILAN-'.$payable->id, 'Cicilan ke-'.$payable->sequence.' Tagihan Daftar Ulang SPMB'],
            default => ['FORMFEE-'.$payable->id, 'Biaya Formulir SPMB'],
        };

        $response = Http::withBasicAuth((string) config('midtrans.server_key'), '')
            ->acceptJson()
            ->post((string) config('midtrans.snap_url'), [
                'transaction_details' => [
                    'order_id' => $payment->reference,
                    'gross_amount' => $payment->amount,
                ],
                'customer_details' => [
                    'first_name' => $guardian->name,
                    'phone' => $guardian->phone,
                    'email' => $guardian->email,
                ],
                'item_details' => [
                    [
                        'id' => $itemId,
                        'price' => $payment->amount,
                        'quantity' => 1,
                        'name' => $itemName,
                    ],
                ],
            ]);

        if ($response->failed() || empty($response->json('token'))) {
            throw new RuntimeException('Midtrans tidak dapat membuat transaksi. Coba lagi atau pilih kanal lain.');
        }

        $payment->update(['snap_token' => $response->json('token')]);

        return [
            'type' => 'snap',
            'snap_token' => $response->json('token'),
            'snap_js_url' => config('midtrans.snap_js_url'),
            'client_key' => config('midtrans.client_key'),
        ];
    }

    /**
     * Verify a notification payload's signature (SHA512 of
     * order_id + status_code + gross_amount + server key).
     */
    public function signatureIsValid(array $payload): bool
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '')
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .(string) config('midtrans.server_key'));

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }
}
