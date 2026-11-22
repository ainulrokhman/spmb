<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappDevice;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Fonnte driver: the cloud gateway at api.fonnte.com. Each
 * device carries its own token, sent as the Authorization
 * header; numbers are paired on Fonnte's own dashboard, so this
 * driver only validates tokens, reads device state, and sends.
 *
 * The send contract mirrors ppdb-alhikmah's production-proven
 * Fonnte usage: POST /send with a JSON {target, message} body
 * and the token as the Authorization header; it answers
 * {status: bool, id, requestid, ...}. POST /device answers
 * with the device's name, number, and connection status.
 * Transport failures are rethrown as WhatsappGatewayException.
 */
class FonnteGateway implements WhatsappGatewayInterface
{
    public function key(): string
    {
        return WhatsappDevice::PROVIDER_FONNTE;
    }

    public function label(): string
    {
        return 'Fonnte (cloud)';
    }

    /**
     * @return array{connected: bool, phone: ?string, name: ?string}
     */
    public function deviceStatus(WhatsappDevice $device): array
    {
        return $this->deviceStatusForToken((string) $device->token);
    }

    /**
     * Device state for a bare token, used to validate a token
     * before its device row exists.
     *
     * @return array{connected: bool, phone: ?string, name: ?string}
     *
     * @throws WhatsappGatewayException when the token is rejected or Fonnte cannot be reached
     */
    public function deviceStatusForToken(string $token): array
    {
        if ($token === '') {
            throw new WhatsappGatewayException('Token device Fonnte belum diisi.');
        }

        $response = $this->call(fn () => $this->request($token)->post($this->url('/device')));

        if ($response->failed() || $response->json('status') !== true) {
            throw new WhatsappGatewayException('Token Fonnte tidak diterima: '.($this->reason($response) ?? 'periksa kembali token device dari dashboard Fonnte.'));
        }

        // The real /device answer is flat: the number itself is
        // the "device" string, the connection state rides in
        // "device_status" ("connect"), and the name is top-level.
        $body = (array) $response->json();
        $nested = $body['device'] ?? null;
        if (is_array($nested)) {
            $phone = $nested['device'] ?? $nested['phone'] ?? null;
            $name = $nested['name'] ?? null;
            $status = strtolower((string) ($nested['device_status'] ?? $nested['status'] ?? ''));
        } else {
            $phone = is_string($nested) ? $nested : ($body['phone'] ?? null);
            $name = $body['name'] ?? null;
            $status = strtolower((string) ($body['device_status'] ?? ''));
        }

        return [
            'connected' => $status !== '' && ! str_contains($status, 'disconnect') && str_contains($status, 'connect'),
            'phone' => $phone !== null ? (string) $phone : null,
            'name' => $name !== null ? (string) $name : null,
        ];
    }

    public function send(WhatsappDevice $device, string $phone, string $message): ?string
    {
        $response = $this->call(fn () => $this->request((string) $device->token)
            ->post($this->url('/send'), [
                'target' => $phone,
                'message' => $message,
            ]));

        if ($response->failed() || $response->json('status') !== true) {
            throw new WhatsappGatewayException('Gagal mengirim pesan lewat Fonnte: '.($this->reason($response) ?? "HTTP {$response->status()}."));
        }

        $id = $response->json('id');
        if (is_array($id)) {
            $id = $id[0] ?? null;
        }
        $id ??= $response->json('requestid');

        return $id !== null ? (string) $id : null;
    }

    /**
     * Fonnte explains refusals in a "reason" (or "message")
     * field; surface it when present.
     */
    private function reason(Response $response): ?string
    {
        foreach (['reason', 'message'] as $field) {
            $value = $response->json($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Run one HTTP call, translating transport failures into the
     * gateway exception type.
     *
     * @param  Closure(): Response  $send
     *
     * @throws WhatsappGatewayException
     */
    private function call(Closure $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException $e) {
            throw new WhatsappGatewayException('Server Fonnte tidak dapat dihubungi. Periksa jaringan, lalu coba lagi.', previous: $e);
        }
    }

    private function request(string $token): PendingRequest
    {
        return Http::timeout((int) config('whatsapp.fonnte.timeout', 15))
            ->acceptJson()
            ->withHeaders(['Authorization' => $token]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('whatsapp.fonnte.base_url'), '/').$path;
    }
}
