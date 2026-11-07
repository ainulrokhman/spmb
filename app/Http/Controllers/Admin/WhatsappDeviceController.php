<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolUnit;
use App\Models\Setting;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Services\Whatsapp\WhatsappGatewayException;
use App\Services\Whatsapp\WhatsappGatewayManager;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * WhatsApp device management: one sender device per school
 * unit plus the foundation fallback device, across both
 * gateway providers (GOWA slots are created and paired here;
 * Fonnte devices are paired on Fonnte's dashboard and only
 * registered here by token). Every change here is visible in
 * the WhatsApp report through the sender service's log.
 */
class WhatsappDeviceController extends Controller
{
    public function __construct(
        private readonly WhatsappGatewayManager $gateways,
        private readonly WhatsappSender $sender,
    ) {}

    /**
     * The foundation device card and the per-unit device list.
     */
    public function index(): View
    {
        $foundation = WhatsappDevice::foundation();
        $units = SchoolUnit::query()
            ->with('whatsappDevice')
            ->orderBy('name')
            ->get();
        $providers = WhatsappDevice::PROVIDERS;
        $enabled = WhatsappSender::isEnabled();

        return view('admin.whatsapp.index', compact('foundation', 'units', 'providers', 'enabled'));
    }

    /**
     * Flip the WhatsApp kill switch. While off, the sender
     * service makes no provider send calls (attempts are logged
     * as skipped); device management stays available so numbers
     * can be prepared or repaired.
     */
    public function toggle(): RedirectResponse
    {
        $enabled = ! WhatsappSender::isEnabled();
        Setting::set('whatsapp_enabled', $enabled ? '1' : '0');

        return back()->with('success', $enabled
            ? 'Fitur WhatsApp diaktifkan. Pesan akan dikirim seperti biasa.'
            : 'Fitur WhatsApp dinonaktifkan. Tidak ada pesan WhatsApp yang akan dikirim sampai diaktifkan kembali.');
    }

    /**
     * Register a device for one unit, or the foundation device
     * when no unit is chosen. GOWA slots are created on the
     * server first; Fonnte tokens are validated against Fonnte
     * before the row is saved.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_unit_id' => ['nullable', 'integer', 'exists:school_units,id'],
            'provider' => ['required', Rule::in(array_keys(WhatsappDevice::PROVIDERS))],
            'display_name' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'token' => ['nullable', 'string', 'max:500', 'required_if:provider,'.WhatsappDevice::PROVIDER_FONNTE],
        ]);

        $unitId = $data['school_unit_id'] ?? null;
        if ($unitId === null && WhatsappDevice::foundation() !== null) {
            return back()->with('error', 'Device yayasan sudah ada. Hapus dulu device yang sekarang bila ingin menggantinya.');
        }
        if ($unitId !== null && WhatsappDevice::forUnit($unitId) !== null) {
            return back()->with('error', 'Unit ini sudah mempunyai device. Hapus dulu device yang sekarang bila ingin menggantinya.');
        }

        try {
            $attributes = $data['provider'] === WhatsappDevice::PROVIDER_GOWA
                ? $this->createGowaDevice($data, $unitId)
                : $this->createFonnteDevice($data);
        } catch (WhatsappGatewayException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        WhatsappDevice::create(array_merge($attributes, [
            'school_unit_id' => $unitId,
            'provider' => $data['provider'],
            'display_name' => $data['display_name'] ?? null,
        ]));

        return redirect()->route('admin.whatsapp.index')
            ->with('success', 'Device WhatsApp berhasil ditambahkan.');
    }

    /**
     * The QR pairing page for a GOWA device.
     */
    public function login(WhatsappDevice $device): View|RedirectResponse
    {
        if ($device->provider !== WhatsappDevice::PROVIDER_GOWA) {
            return redirect()->route('admin.whatsapp.index')
                ->with('error', 'Device Fonnte dipasangkan dari dashboard Fonnte, bukan dari halaman ini.');
        }

        return view('admin.whatsapp.login', compact('device'));
    }

    /**
     * Stream the GOWA QR pairing image for one device.
     */
    public function qr(WhatsappDevice $device): Response
    {
        if ($device->provider !== WhatsappDevice::PROVIDER_GOWA) {
            abort(404);
        }

        try {
            $image = $this->gateways->gowa()->loginQrImage($device);
        } catch (WhatsappGatewayException $e) {
            return response($e->getMessage(), 422, ['Content-Type' => 'text/plain']);
        }

        return response($image['bytes'], 200, [
            'Content-Type' => $image['content_type'],
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Re-read one device's live status from its provider.
     */
    public function refresh(WhatsappDevice $device): RedirectResponse
    {
        $status = $this->sender->refreshStatus($device);

        return back()->with(
            $status['connected'] ? 'success' : 'error',
            $status['connected']
                ? "Device {$device->label()} terhubung ke WhatsApp."
                : "Device {$device->label()} sedang tidak terhubung ke WhatsApp."
        );
    }

    /**
     * Reconnect a device (GOWA slots are asked to reconnect;
     * Fonnte devices are simply re-checked).
     */
    public function reconnect(WhatsappDevice $device): RedirectResponse
    {
        if ($device->provider === WhatsappDevice::PROVIDER_GOWA) {
            try {
                $this->gateways->gowa()->reconnect($device);
            } catch (WhatsappGatewayException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return $this->refresh($device);
    }

    /**
     * Log a GOWA device out of WhatsApp (its slot is kept, so
     * it can pair again from the login page).
     */
    public function logout(WhatsappDevice $device): RedirectResponse
    {
        if ($device->provider !== WhatsappDevice::PROVIDER_GOWA) {
            return back()->with('error', 'Device Fonnte diputuskan dari dashboard Fonnte.');
        }

        try {
            $this->gateways->gowa()->logout($device);
        } catch (WhatsappGatewayException $e) {
            return back()->with('error', $e->getMessage());
        }

        $device->update([
            'status' => WhatsappDevice::STATUS_DISCONNECTED,
            'last_checked_at' => now(),
        ]);

        return back()->with('success', "Device {$device->label()} berhasil diputuskan dari WhatsApp.");
    }

    /**
     * Send one test message through a specific device.
     */
    public function test(Request $request, WhatsappDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $log = $this->sender->sendViaDevice($device, $data['phone'], $data['message']);

        return back()->with(
            $log->status === WhatsappMessageLog::STATUS_SENT ? 'success' : 'error',
            $log->status === WhatsappMessageLog::STATUS_SENT
                ? "Pesan uji terkirim ke {$log->recipient_phone} lewat device {$device->label()}."
                : 'Pesan uji tidak terkirim: '.($log->error ?? 'penyebab tidak diketahui.')
        );
    }

    /**
     * Remove a device. GOWA slots are removed from the server
     * too; when the server cannot be reached the local record is
     * still removed and the admin is told.
     */
    public function destroy(WhatsappDevice $device): RedirectResponse
    {
        $warning = null;
        if ($device->provider === WhatsappDevice::PROVIDER_GOWA) {
            try {
                $this->gateways->gowa()->removeDevice($device);
            } catch (WhatsappGatewayException $e) {
                $warning = ' Device di server GOWA tidak ikut terhapus: '.$e->getMessage();
            }
        }

        $label = $device->label();
        $device->delete();

        return redirect()->route('admin.whatsapp.index')
            ->with('success', "Device {$label} berhasil dihapus.".$warning);
    }

    /**
     * Create the slot on the GOWA server and return the device
     * row attributes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createGowaDevice(array $data, ?int $unitId): array
    {
        $deviceId = $data['device_id'] ?? null;
        if ($deviceId === null || $deviceId === '') {
            $deviceId = $unitId === null
                ? 'yayasan'
                : 'unit-'.SchoolUnit::query()->whereKey($unitId)->value('code');
        }

        $this->gateways->gowa()->addDevice($deviceId);

        return [
            'device_id' => $deviceId,
            'status' => WhatsappDevice::STATUS_UNKNOWN,
        ];
    }

    /**
     * Validate a Fonnte token against Fonnte and return the
     * device row attributes from its answer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function createFonnteDevice(array $data): array
    {
        $status = $this->gateways->fonnte()->deviceStatusForToken((string) $data['token']);

        return [
            'token' => $data['token'],
            'phone' => $status['phone'],
            'status' => $status['connected'] ? WhatsappDevice::STATUS_CONNECTED : WhatsappDevice::STATUS_DISCONNECTED,
            'last_checked_at' => now(),
        ];
    }
}
