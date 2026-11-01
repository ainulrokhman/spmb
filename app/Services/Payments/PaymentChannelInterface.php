<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * A payment channel driver. Every channel implements the same
 * contract — create a payment, then report its outcome — so new
 * channels (e.g. the BSI custom integration) plug in without
 * touching the registration flow (Bab 6.4 of the design doc).
 */
interface PaymentChannelInterface
{
    /**
     * The channel's stable key, stored on payments.
     */
    public function key(): string;

    /**
     * The channel's display label.
     */
    public function label(): string;

    /**
     * Whether the channel can currently be chosen by guardians.
     */
    public function isEnabled(): bool;

    /**
     * Start the payment on the channel side and return view data for
     * the guardian payment page (instructions, snap token, ...).
     *
     * @return array<string, mixed>
     */
    public function createPayment(Payment $payment): array;
}
