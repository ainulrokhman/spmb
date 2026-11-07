<?php

namespace Tests\Feature;

use App\Models\SchoolUnit;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappSenderTest extends TestCase
{
    use RefreshDatabase;

    private SchoolUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.gowa.base_url' => 'http://gowa.test',
            'whatsapp.gowa.username' => 'admin',
            'whatsapp.gowa.password' => 'rahasia',
            'whatsapp.fonnte.base_url' => 'https://api.fonnte.com',
        ]);

        $this->unit = SchoolUnit::factory()->create(['code' => '33']);
    }

    private function gowaDevice(?int $unitId, string $deviceId): WhatsappDevice
    {
        return WhatsappDevice::create([
            'school_unit_id' => $unitId,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => $deviceId,
        ]);
    }

    /**
     * Fake the GOWA server: /app/status answers per device id
     * from $loginStates (device_id => logged in), /send/message
     * records sends and succeeds unless $sendFails.
     *
     * @param  array<string, bool>  $loginStates
     */
    private function fakeGowa(array $loginStates, bool $sendFails = false): void
    {
        Http::fake(function (Request $request) use ($loginStates, $sendFails) {
            $deviceId = $request->header('X-Device-Id')[0] ?? null;

            if (str_ends_with($request->url(), '/app/status')) {
                $loggedIn = $loginStates[$deviceId] ?? false;

                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => [
                        'is_connected' => true,
                        'is_logged_in' => $loggedIn,
                        'jid' => $loggedIn ? '628987654321@s.whatsapp.net' : null,
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/send/message')) {
                if ($sendFails) {
                    return Http::response(['status' => 500, 'code' => 'ERROR', 'message' => 'gagal total'], 500);
                }

                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => ['id' => 'MSG-'.$deviceId],
                ]);
            }

            return Http::response([], 404);
        });
    }

    public function test_sends_through_the_units_own_connected_device(): void
    {
        $device = $this->gowaDevice($this->unit->id, 'unit-33');
        $this->gowaDevice(null, 'yayasan');
        $this->fakeGowa(['unit-33' => true, 'yayasan' => true]);

        $log = app(WhatsappSender::class)->send($this->unit->id, '081234567890', 'Halo wali', WhatsappMessageLog::TYPE_PAYMENT, 'Wali A');

        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertSame($device->id, $log->whatsapp_device_id);
        $this->assertSame($this->unit->id, $log->school_unit_id);
        $this->assertSame('6281234567890', $log->recipient_phone);
        $this->assertSame('MSG-unit-33', $log->provider_message_id);
        $this->assertFalse($log->sentViaFoundationFallback());
        $this->assertSame(WhatsappDevice::STATUS_CONNECTED, $device->fresh()->status);
        $this->assertSame('628987654321', $device->fresh()->phone);
    }

    public function test_falls_back_to_foundation_when_unit_device_is_disconnected(): void
    {
        $this->gowaDevice($this->unit->id, 'unit-33');
        $foundation = $this->gowaDevice(null, 'yayasan');
        $this->fakeGowa(['unit-33' => false, 'yayasan' => true]);

        $log = app(WhatsappSender::class)->send($this->unit->id, '081234567890', 'Halo', WhatsappMessageLog::TYPE_PAYMENT);

        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertSame($foundation->id, $log->whatsapp_device_id);
        $this->assertTrue($log->sentViaFoundationFallback());
    }

    public function test_unit_without_device_uses_foundation_directly(): void
    {
        $foundation = $this->gowaDevice(null, 'yayasan');
        $this->fakeGowa(['yayasan' => true]);

        $log = app(WhatsappSender::class)->send($this->unit->id, '081234567890', 'Halo', WhatsappMessageLog::TYPE_RESULT);

        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertSame($foundation->id, $log->whatsapp_device_id);
    }

    public function test_skips_and_logs_when_no_device_is_connected(): void
    {
        $this->gowaDevice($this->unit->id, 'unit-33');
        $this->fakeGowa(['unit-33' => false]);

        $log = app(WhatsappSender::class)->send($this->unit->id, '081234567890', 'Halo', WhatsappMessageLog::TYPE_PAYMENT);

        $this->assertSame(WhatsappMessageLog::STATUS_SKIPPED, $log->status);
        $this->assertNull($log->whatsapp_device_id);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/send/message'));
    }

    public function test_provider_refusal_is_logged_failed_without_retrying_from_foundation(): void
    {
        $device = $this->gowaDevice($this->unit->id, 'unit-33');
        $this->gowaDevice(null, 'yayasan');
        $this->fakeGowa(['unit-33' => true, 'yayasan' => true], sendFails: true);

        $log = app(WhatsappSender::class)->send($this->unit->id, '081234567890', 'Halo', WhatsappMessageLog::TYPE_PAYMENT);

        $this->assertSame(WhatsappMessageLog::STATUS_FAILED, $log->status);
        $this->assertSame($device->id, $log->whatsapp_device_id);
        $this->assertStringContainsString('gagal total', (string) $log->error);
        $this->assertSame(1, WhatsappMessageLog::count());
    }

    public function test_invalid_number_is_skipped_without_any_http_call(): void
    {
        $this->gowaDevice(null, 'yayasan');
        Http::fake();

        $log = app(WhatsappSender::class)->send(null, '021123456', 'Halo', WhatsappMessageLog::TYPE_MANUAL);

        $this->assertSame(WhatsappMessageLog::STATUS_SKIPPED, $log->status);
        Http::assertNothingSent();
    }

    public function test_foundation_context_send_uses_foundation_device(): void
    {
        $foundation = WhatsappDevice::create([
            'provider' => WhatsappDevice::PROVIDER_FONNTE,
            'token' => 'token-yayasan',
        ]);
        Http::fake([
            'api.fonnte.com/device' => Http::response([
                'status' => true,
                'device' => ['name' => 'Yayasan', 'device' => '628987654321', 'status' => 'connected'],
            ]),
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['F-1']]),
        ]);

        $log = app(WhatsappSender::class)->send(null, '081234567890', 'Halo', WhatsappMessageLog::TYPE_MANUAL);

        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertSame($foundation->id, $log->whatsapp_device_id);
        $this->assertNull($log->school_unit_id);
        $this->assertSame('F-1', $log->provider_message_id);
    }

    public function test_send_via_device_reports_failed_when_device_disconnected(): void
    {
        $device = $this->gowaDevice($this->unit->id, 'unit-33');
        $this->fakeGowa(['unit-33' => false]);

        $log = app(WhatsappSender::class)->sendViaDevice($device, '081234567890', 'Uji');

        $this->assertSame(WhatsappMessageLog::STATUS_FAILED, $log->status);
        $this->assertSame(WhatsappMessageLog::TYPE_TEST, $log->type);
        $this->assertSame($device->id, $log->whatsapp_device_id);
    }

    public function test_unreachable_provider_counts_as_not_connected(): void
    {
        $this->gowaDevice($this->unit->id, 'unit-33');
        $foundation = $this->gowaDevice(null, 'yayasan');
        Http::fake(function (Request $request) {
            if (($request->header('X-Device-Id')[0] ?? null) === 'unit-33') {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            }

            return Http::response([
                'status' => 200,
                'code' => 'SUCCESS',
                'results' => ['is_connected' => true, 'is_logged_in' => true, 'jid' => '628987654321@s.whatsapp.net'],
            ]);
        });

        $log = app(WhatsappSender::class)->send($this->unit->id, '081234567890', 'Halo', WhatsappMessageLog::TYPE_PAYMENT);

        // The unit device never answers (transport failure), so the
        // foundation device must be the recorded sender.
        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertSame($foundation->id, $log->whatsapp_device_id);
    }
}
