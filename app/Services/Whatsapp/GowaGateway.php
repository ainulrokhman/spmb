<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappDevice;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * GOWA driver: talks to the foundation's self-hosted
 * go-whatsapp-web-multidevice server. One Basic Auth credential
 * set (from .env) guards the whole server; each device row
 * addresses one device slot through the X-Device-Id header.
 *
 * Contracts follow the project's OpenAPI (v9): calls answer
 * with a {status, code: "SUCCESS", message, results} envelope
 * and a device's connection state is read from GET /app/status,
 * whose results carry is_connected / is_logged_in / jid.
 * Transport failures are rethrown as WhatsappGatewayException
 * so callers never see a raw HTTP client exception.
 */
class GowaGateway implements WhatsappGatewayInterface
{
    public function key(): string
    {
        return WhatsappDevice::PROVIDER_GOWA;
    }

    public function label(): string
    {
        return 'GOWA (server sendiri)';
    }

    /**
     * @return array{connected: bool, phone: ?string, name: ?string}
     */
    public function deviceStatus(WhatsappDevice $device): array
    {
        $response = $this->call(fn () => $this->request($device)->get($this->url('/app/status')));

        $this->ensureSuccess($response, 'memeriksa status device GOWA');

        $results = (array) $response->json('results', []);
        $jid = $results['jid'] ?? null;

        return [
            'connected' => (bool) ($results['is_logged_in'] ?? $results['is_connected'] ?? false),
            'phone' => $jid ? explode('@', (string) $jid)[0] : null,
            'name' => null,
        ];
    }

    public function send(WhatsappDevice $device, string $phone, string $message): ?string
    {
        $response = $this->call(fn () => $this->request($device)->post($this->url('/send/message'), [
            'phone_number' => $phone,
            'message' => $message,
        ]));

        $this->ensureSuccess($response, 'mengirim pesan lewat GOWA');

        $id = $response->json('results.id');

        return $id !== null ? (string) $id : null;
    }

    /**
     * Create a device slot on the GOWA server.
     *
     * @throws WhatsappGatewayException
     */
    public function addDevice(string $deviceId): void
    {
        $response = $this->call(fn () => $this->request(null)->post($this->url('/devices'), [
            'device_id' => $deviceId,
        ]));

        $this->ensureSuccess($response, 'membuat device di server GOWA');
    }

    /**
     * Fetch the QR pairing image for one device slot, as raw
     * bytes ready to stream to the admin's browser. The device
     * login endpoint answers with a link to the QR image; the
     * image itself is fetched here (with credentials) so the
     * browser never needs the GOWA password.
     *
     * @return array{bytes: string, content_type: string}
     *
     * @throws WhatsappGatewayException
     */
    public function loginQrImage(WhatsappDevice $device): array
    {
        $response = $this->call(fn () => $this->request($device)->get($this->url("/devices/{$device->device_id}/login")));

        $contentType = (string) $response->header('Content-Type');
        if (str_starts_with($contentType, 'image/')) {
            return ['bytes' => $response->body(), 'content_type' => $contentType];
        }

        $this->ensureSuccess($response, 'mengambil kode QR GOWA');

        $link = $response->json('results.qr_link') ?? $response->json('results.qr_code');
        if (! is_string($link) || $link === '') {
            throw new WhatsappGatewayException('Server GOWA tidak memberikan kode QR. Pastikan device belum terhubung, lalu coba lagi.');
        }

        if (str_starts_with($link, 'data:image')) {
            $bytes = base64_decode(substr($link, strpos($link, ',') + 1), true);
            if ($bytes === false) {
                throw new WhatsappGatewayException('Kode QR dari server GOWA tidak dapat dibaca.');
            }

            return ['bytes' => $bytes, 'content_type' => 'image/png'];
        }

        $image = $this->call(fn () => $this->request(null)->get($link));
        if (! $image->successful()) {
            throw new WhatsappGatewayException('Gambar kode QR tidak dapat diambil dari server GOWA.');
        }

        return [
            'bytes' => $image->body(),
            'content_type' => (string) ($image->header('Content-Type') ?: 'image/png'),
        ];
    }

    /**
     * Ask the device slot to reconnect to WhatsApp.
     *
     * @throws WhatsappGatewayException
     */
    public function reconnect(WhatsappDevice $device): void
    {
        $response = $this->call(fn () => $this->request($device)->post($this->url("/devices/{$device->device_id}/reconnect")));

        $this->ensureSuccess($response, 'menyambung ulang device GOWA');
    }

    /**
     * Log the device slot out of WhatsApp (the slot itself is
     * kept on the server and can pair again).
     *
     * @throws WhatsappGatewayException
     */
    public function logout(WhatsappDevice $device): void
    {
        $response = $this->call(fn () => $this->request($device)->post($this->url("/devices/{$device->device_id}/logout")));

        $this->ensureSuccess($response, 'memutuskan device GOWA');
    }

    /**
     * Remove the device slot from the GOWA server entirely.
     *
     * @throws WhatsappGatewayException
     */
    public function removeDevice(WhatsappDevice $device): void
    {
        $response = $this->call(fn () => $this->request($device)->delete($this->url("/devices/{$device->device_id}")));

        $this->ensureSuccess($response, 'menghapus device di server GOWA');
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
            throw new WhatsappGatewayException('Server GOWA tidak dapat dihubungi. Periksa alamat server dan jaringan, lalu coba lagi.', previous: $e);
        }
    }

    /**
     * A pre-configured HTTP client for the GOWA server, scoped
     * to one device when given.
     *
     * @throws WhatsappGatewayException when the server is not configured
     */
    private function request(?WhatsappDevice $device): PendingRequest
    {
        if (! config('whatsapp.gowa.base_url')) {
            throw new WhatsappGatewayException('Server GOWA belum dikonfigurasi. Isi GOWA_BASE_URL, GOWA_USERNAME, dan GOWA_PASSWORD di .env.');
        }

        $request = Http::timeout((int) config('whatsapp.gowa.timeout', 15))
            ->acceptJson()
            ->withBasicAuth(
                (string) config('whatsapp.gowa.username'),
                (string) config('whatsapp.gowa.password'),
            );

        if ($device?->device_id) {
            $request = $request->withHeaders(['X-Device-Id' => $device->device_id]);
        }

        return $request;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('whatsapp.gowa.base_url'), '/').$path;
    }

    /**
     * @throws WhatsappGatewayException
     */
    private function ensureSuccess(Response $response, string $action): void
    {
        if ($response->failed()) {
            $message = $response->json('message');

            throw new WhatsappGatewayException("Gagal {$action}: ".(is_string($message) && $message !== '' ? "{$message} (HTTP {$response->status()})." : "server GOWA menjawab HTTP {$response->status()}."));
        }

        $code = $response->json('code');
        if ($code !== null && $code !== 'SUCCESS') {
            $message = $response->json('message');

            throw new WhatsappGatewayException("Gagal {$action}: ".(is_string($message) && $message !== '' ? $message : "kode {$code}."));
        }
    }
}
