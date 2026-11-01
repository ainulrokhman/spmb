<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Setting;

/**
 * Manual channel: the guardian pays according to the foundation's
 * instructions (bank transfer or cash at the school) and the
 * committee confirms receipt in the system.
 */
class ManualChannel implements PaymentChannelInterface
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manual (Transfer / Tunai)';
    }

    public function isEnabled(): bool
    {
        return Setting::flag('payment_manual_enabled', true);
    }

    public function createPayment(Payment $payment): array
    {
        return [
            'type' => 'instructions',
            'instructions' => Setting::get(
                'payment_manual_instructions',
                'Transfer ke rekening yayasan atau bayar tunai di sekolah, lalu tekan tombol "Saya Sudah Bayar" agar panitia dapat mengonfirmasi.'
            ),
        ];
    }
}
