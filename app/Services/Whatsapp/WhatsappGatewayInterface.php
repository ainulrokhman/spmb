<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappDevice;

/**
 * A WhatsApp gateway provider (driver). The rest of the system —
 * device management, the sender service, OTP, templates, the
 * report — only ever talks to this contract, so GOWA and Fonnte
 * devices can be mixed freely across units.
 */
interface WhatsappGatewayInterface
{
    /**
     * The provider key stored on the device row ('gowa' / 'fonnte').
     */
    public function key(): string;

    /**
     * Human label for the provider.
     */
    public function label(): string;

    /**
     * Live status of one device.
     *
     * @return array{connected: bool, phone: ?string, name: ?string}
     *
     * @throws WhatsappGatewayException when the provider cannot be reached at all
     */
    public function deviceStatus(WhatsappDevice $device): array;

    /**
     * Send one text message. Returns the provider's message id
     * when it issues one.
     *
     * @throws WhatsappGatewayException when the provider refuses or cannot send
     */
    public function send(WhatsappDevice $device, string $phone, string $message): ?string;
}
