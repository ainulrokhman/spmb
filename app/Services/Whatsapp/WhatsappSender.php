<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * The single door for sending WhatsApp messages. It resolves
 * which device sends — the unit's own device first, then the
 * foundation device as the shared fallback — asks the chosen
 * provider, and writes exactly one log row per message
 * (sent / failed / skipped) for the WhatsApp report.
 *
 * Sending never throws: a WhatsApp problem must not break the
 * flow that triggered the message (registration, payment, …).
 * Callers that must know the outcome (the OTP flow) read the
 * returned log's status.
 */
class WhatsappSender
{
    public function __construct(
        private readonly WhatsappGatewayManager $gateways,
    ) {}

    /**
     * The feature kill switch (Pengaturan key whatsapp_enabled,
     * toggled from the WhatsApp page). While off, no provider
     * send call is made at all; attempts are still logged as
     * skipped so the report shows what was suppressed.
     */
    public static function isEnabled(): bool
    {
        return Setting::flag('whatsapp_enabled', true);
    }

    /**
     * Send a message belonging to a unit (or to the foundation
     * when $schoolUnitId is null) through the resolution chain.
     */
    public function send(?int $schoolUnitId, string $phone, string $message, string $type, ?string $recipientName = null): WhatsappMessageLog
    {
        if (! self::isEnabled()) {
            return $this->log(null, $schoolUnitId, PhoneNumber::toWhatsApp($phone) ?? $phone, $recipientName, $type, $message, WhatsappMessageLog::STATUS_SKIPPED, 'Fitur WhatsApp sedang dinonaktifkan.');
        }

        $target = PhoneNumber::toWhatsApp($phone);
        if ($target === null) {
            return $this->log(null, $schoolUnitId, $phone, $recipientName, $type, $message, WhatsappMessageLog::STATUS_SKIPPED, 'Nomor tujuan tidak valid sebagai nomor WhatsApp.');
        }

        $candidates = [];
        if ($schoolUnitId !== null) {
            $unitDevice = WhatsappDevice::forUnit($schoolUnitId);
            if ($unitDevice !== null) {
                $candidates[] = $unitDevice;
            }
        }
        $foundation = WhatsappDevice::foundation();
        if ($foundation !== null && ! in_array($foundation->id, array_column($candidates, 'id'), true)) {
            $candidates[] = $foundation;
        }

        foreach ($candidates as $device) {
            $status = $this->refreshStatus($device);
            if (! $status['connected']) {
                continue;
            }

            return $this->deliver($device, $schoolUnitId, $target, $recipientName, $type, $message);
        }

        return $this->log(null, $schoolUnitId, $target, $recipientName, $type, $message, WhatsappMessageLog::STATUS_SKIPPED, 'Tidak ada device WhatsApp yang terhubung untuk unit ini maupun yayasan.');
    }

    /**
     * Send through one specific device, bypassing the chain —
     * the admin test message. The outcome is still logged.
     */
    public function sendViaDevice(WhatsappDevice $device, string $phone, string $message, string $type = WhatsappMessageLog::TYPE_TEST, ?string $recipientName = null): WhatsappMessageLog
    {
        if (! self::isEnabled()) {
            return $this->log($device, $device->school_unit_id, PhoneNumber::toWhatsApp($phone) ?? $phone, $recipientName, $type, $message, WhatsappMessageLog::STATUS_SKIPPED, 'Fitur WhatsApp sedang dinonaktifkan.');
        }

        $target = PhoneNumber::toWhatsApp($phone);
        if ($target === null) {
            return $this->log($device, $device->school_unit_id, $phone, $recipientName, $type, $message, WhatsappMessageLog::STATUS_SKIPPED, 'Nomor tujuan tidak valid sebagai nomor WhatsApp.');
        }

        $status = $this->refreshStatus($device);
        if (! $status['connected']) {
            return $this->log($device, $device->school_unit_id, $target, $recipientName, $type, $message, WhatsappMessageLog::STATUS_FAILED, 'Device tidak terhubung ke WhatsApp.');
        }

        return $this->deliver($device, $device->school_unit_id, $target, $recipientName, $type, $message);
    }

    /**
     * Ask the device's provider for its live status and cache
     * the outcome on the device row. A provider that cannot be
     * reached counts as not connected for routing purposes and
     * is cached as "unknown".
     *
     * @return array{connected: bool, phone: ?string, name: ?string}
     */
    public function refreshStatus(WhatsappDevice $device): array
    {
        try {
            $status = $this->gateways->forDevice($device)->deviceStatus($device);
        } catch (WhatsappGatewayException $e) {
            $device->update([
                'status' => WhatsappDevice::STATUS_UNKNOWN,
                'last_checked_at' => now(),
            ]);

            return ['connected' => false, 'phone' => null, 'name' => null];
        }

        $device->update([
            'status' => $status['connected'] ? WhatsappDevice::STATUS_CONNECTED : WhatsappDevice::STATUS_DISCONNECTED,
            'phone' => $status['phone'] ?? $device->phone,
            'display_name' => $device->display_name ?: ($status['name'] ?? null),
            'last_checked_at' => now(),
        ]);

        return $status;
    }

    /**
     * Send through an already-chosen, connected device and log
     * the outcome. A provider refusal is a failed send: the
     * message is not silently retried from another number,
     * because the provider may already have accepted it.
     */
    private function deliver(WhatsappDevice $device, ?int $schoolUnitId, string $target, ?string $recipientName, string $type, string $message): WhatsappMessageLog
    {
        try {
            $providerMessageId = $this->gateways->forDevice($device)->send($device, $target, $message);
        } catch (WhatsappGatewayException $e) {
            Log::warning('Pengiriman WhatsApp gagal.', [
                'device_id' => $device->id,
                'provider' => $device->provider,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return $this->log($device, $schoolUnitId, $target, $recipientName, $type, $message, WhatsappMessageLog::STATUS_FAILED, $e->getMessage());
        }

        return $this->log($device, $schoolUnitId, $target, $recipientName, $type, $message, WhatsappMessageLog::STATUS_SENT, null, $providerMessageId);
    }

    private function log(?WhatsappDevice $device, ?int $schoolUnitId, string $phone, ?string $recipientName, string $type, string $message, string $status, ?string $error, ?string $providerMessageId = null): WhatsappMessageLog
    {
        return WhatsappMessageLog::create([
            'whatsapp_device_id' => $device?->id,
            'school_unit_id' => $schoolUnitId,
            'recipient_phone' => $phone,
            'recipient_name' => $recipientName,
            'type' => $type,
            'message' => $message,
            'status' => $status,
            'error' => $error,
            'provider_message_id' => $providerMessageId,
        ]);
    }
}
