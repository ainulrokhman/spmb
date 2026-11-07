<?php

namespace Tests\Feature;

use App\Models\WhatsappDevice;
use App\Services\Whatsapp\FonnteGateway;
use App\Services\Whatsapp\GowaGateway;
use App\Services\Whatsapp\WhatsappGatewayException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.gowa.base_url' => 'http://gowa.test',
            'whatsapp.gowa.username' => 'admin',
            'whatsapp.gowa.password' => 'rahasia',
            'whatsapp.fonnte.base_url' => 'https://api.fonnte.com',
        ]);
    }

    private function gowaDevice(array $attributes = []): WhatsappDevice
    {
        return WhatsappDevice::create(array_merge([
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'unit-33',
        ], $attributes));
    }

    private function fonnteDevice(array $attributes = []): WhatsappDevice
    {
        return WhatsappDevice::create(array_merge([
            'provider' => WhatsappDevice::PROVIDER_FONNTE,
            'token' => 'token-device-123',
        ], $attributes));
    }

    public function test_gowa_send_posts_to_send_message_with_device_header(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response([
                'status' => 200,
                'code' => 'SUCCESS',
                'message' => 'ok',
                'results' => ['id' => 'MSG-1', 'status' => 'sent'],
            ]),
        ]);

        $id = app(GowaGateway::class)->send($this->gowaDevice(), '6281234567890', 'Halo');

        $this->assertSame('MSG-1', $id);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://gowa.test/send/message'
                && $request->header('X-Device-Id') === ['unit-33']
                && $request->hasHeader('Authorization')
                && $request['phone_number'] === '6281234567890'
                && $request['message'] === 'Halo';
        });
    }

    public function test_gowa_send_throws_on_error_envelope(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response([
                'status' => 400,
                'code' => 'ERROR',
                'message' => 'Device tidak terhubung',
            ], 400),
        ]);

        $this->expectException(WhatsappGatewayException::class);

        app(GowaGateway::class)->send($this->gowaDevice(), '6281234567890', 'Halo');
    }

    public function test_gowa_status_reads_app_status_results(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response([
                'status' => 200,
                'code' => 'SUCCESS',
                'results' => [
                    'is_connected' => true,
                    'is_logged_in' => true,
                    'device_id' => 'unit-33',
                    'jid' => '628987654321@s.whatsapp.net',
                ],
            ]),
        ]);

        $status = app(GowaGateway::class)->deviceStatus($this->gowaDevice());

        $this->assertTrue($status['connected']);
        $this->assertSame('628987654321', $status['phone']);
    }

    public function test_gowa_status_reports_logged_out_device(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response([
                'status' => 200,
                'code' => 'SUCCESS',
                'results' => ['is_connected' => true, 'is_logged_in' => false, 'jid' => null],
            ]),
        ]);

        $status = app(GowaGateway::class)->deviceStatus($this->gowaDevice());

        $this->assertFalse($status['connected']);
        $this->assertNull($status['phone']);
    }

    public function test_gowa_add_device_posts_the_slot(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response(['status' => 200, 'code' => 'SUCCESS', 'message' => 'created']),
        ]);

        app(GowaGateway::class)->addDevice('unit-33');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://gowa.test/devices'
            && $request['device_id'] === 'unit-33');
    }

    public function test_gowa_without_configuration_throws_readable_error(): void
    {
        config(['whatsapp.gowa.base_url' => null]);

        $this->expectException(WhatsappGatewayException::class);
        $this->expectExceptionMessageMatches('/belum dikonfigurasi/');

        app(GowaGateway::class)->deviceStatus($this->gowaDevice());
    }

    public function test_fonnte_send_posts_form_with_token_header(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'id' => ['987'],
                'requestid' => 55,
                'target' => ['6281234567890'],
            ]),
        ]);

        $id = app(FonnteGateway::class)->send($this->fonnteDevice(), '6281234567890', 'Halo');

        $this->assertSame('987', $id);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.fonnte.com/send'
                && $request->header('Authorization') === ['token-device-123']
                && $request['target'] === '6281234567890'
                && $request['message'] === 'Halo';
        });
    }

    public function test_fonnte_send_throws_with_reason_on_refusal(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'device disconnected']),
        ]);

        try {
            app(FonnteGateway::class)->send($this->fonnteDevice(), '6281234567890', 'Halo');
            $this->fail('Expected WhatsappGatewayException');
        } catch (WhatsappGatewayException $e) {
            $this->assertStringContainsString('device disconnected', $e->getMessage());
        }
    }

    public function test_fonnte_status_parses_device_payload(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'device' => ['name' => 'WA SMP', 'device' => '628987654321', 'status' => 'connected'],
            ]),
        ]);

        $status = app(FonnteGateway::class)->deviceStatus($this->fonnteDevice());

        $this->assertTrue($status['connected']);
        $this->assertSame('628987654321', $status['phone']);
        $this->assertSame('WA SMP', $status['name']);
    }

    public function test_fonnte_status_for_token_rejects_bad_token(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'invalid token'], 400),
        ]);

        $this->expectException(WhatsappGatewayException::class);

        app(FonnteGateway::class)->deviceStatusForToken('token-salah');
    }
}
