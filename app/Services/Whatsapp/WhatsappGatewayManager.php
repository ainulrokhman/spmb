<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappDevice;
use InvalidArgumentException;

/**
 * Resolves the gateway driver for a device row or a provider
 * key, so the rest of the system never branches on providers.
 */
class WhatsappGatewayManager
{
    public function __construct(
        private readonly GowaGateway $gowa,
        private readonly FonnteGateway $fonnte,
    ) {}

    public function forDevice(WhatsappDevice $device): WhatsappGatewayInterface
    {
        return $this->forProvider($device->provider);
    }

    public function forProvider(string $provider): WhatsappGatewayInterface
    {
        return match ($provider) {
            WhatsappDevice::PROVIDER_GOWA => $this->gowa,
            WhatsappDevice::PROVIDER_FONNTE => $this->fonnte,
            default => throw new InvalidArgumentException("Provider WhatsApp tidak dikenal: {$provider}"),
        };
    }

    public function gowa(): GowaGateway
    {
        return $this->gowa;
    }

    public function fonnte(): FonnteGateway
    {
        return $this->fonnte;
    }
}
