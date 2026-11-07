<?php

namespace Tests\Feature;

use App\Models\SchoolUnit;
use App\Models\User;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class WhatsappDeviceManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private SchoolUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        config([
            'whatsapp.gowa.base_url' => 'http://gowa.test',
            'whatsapp.gowa.username' => 'admin',
            'whatsapp.gowa.password' => 'rahasia',
            'whatsapp.fonnte.base_url' => 'https://api.fonnte.com',
        ]);

        $this->unit = SchoolUnit::factory()->create(['code' => '33']);
    }

    private function panitia(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Panitia');

        return $user;
    }

    private function fakeGowaOk(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response(['status' => 200, 'code' => 'SUCCESS', 'message' => 'ok', 'results' => new \stdClass]),
        ]);
    }

    public function test_guests_and_unprivileged_users_cannot_open_the_page(): void
    {
        $this->get(route('admin.whatsapp.index'))->assertRedirect(route('login'));

        $this->actingAs($this->panitia())
            ->get(route('admin.whatsapp.index'))
            ->assertForbidden();

        $this->actingAs($this->regularUser())
            ->get(route('admin.whatsapp.index'))
            ->assertForbidden();
    }

    public function test_admin_and_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.index'))
            ->assertOk()
            ->assertSee('Device Yayasan')
            ->assertSee('Device per Unit');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.whatsapp.index'))
            ->assertOk();
    }

    public function test_store_gowa_foundation_device_creates_the_slot_and_row(): void
    {
        $this->fakeGowaOk();

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), [
                'provider' => 'gowa',
                'display_name' => 'WA Yayasan',
            ])
            ->assertRedirect(route('admin.whatsapp.index'))
            ->assertSessionHas('success');

        $device = WhatsappDevice::foundation();
        $this->assertNotNull($device);
        $this->assertSame('yayasan', $device->device_id);
        $this->assertSame('WA Yayasan', $device->display_name);
        Http::assertSent(fn ($request) => $request->url() === 'http://gowa.test/devices' && $request['device_id'] === 'yayasan');
    }

    public function test_store_second_foundation_device_is_rejected(): void
    {
        WhatsappDevice::create(['provider' => 'gowa', 'device_id' => 'yayasan']);
        $this->fakeGowaOk();

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), ['provider' => 'gowa'])
            ->assertSessionHas('error');

        $this->assertSame(1, WhatsappDevice::whereNull('school_unit_id')->count());
    }

    public function test_store_gowa_device_for_unit_uses_unit_code(): void
    {
        $this->fakeGowaOk();

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), [
                'school_unit_id' => $this->unit->id,
                'provider' => 'gowa',
            ])
            ->assertSessionHas('success');

        $device = WhatsappDevice::forUnit($this->unit->id);
        $this->assertNotNull($device);
        $this->assertSame('unit-33', $device->device_id);
    }

    public function test_store_second_device_for_same_unit_is_rejected(): void
    {
        WhatsappDevice::create([
            'school_unit_id' => $this->unit->id,
            'provider' => 'gowa',
            'device_id' => 'unit-33',
        ]);
        $this->fakeGowaOk();

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), [
                'school_unit_id' => $this->unit->id,
                'provider' => 'gowa',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, WhatsappDevice::where('school_unit_id', $this->unit->id)->count());
    }

    public function test_store_gowa_failure_leaves_no_row(): void
    {
        Http::fake([
            'gowa.test/*' => Http::response(['status' => 500, 'code' => 'ERROR', 'message' => 'server sibuk'], 500),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), ['provider' => 'gowa'])
            ->assertSessionHas('error');

        $this->assertSame(0, WhatsappDevice::count());
    }

    public function test_store_fonnte_device_validates_token_and_encrypts_it(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response([
                'status' => true,
                'device' => ['name' => 'WA Yayasan', 'device' => '628987654321', 'status' => 'connected'],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), [
                'provider' => 'fonnte',
                'token' => 'token-rahasia-abc',
            ])
            ->assertSessionHas('success');

        $device = WhatsappDevice::foundation();
        $this->assertNotNull($device);
        $this->assertSame('628987654321', $device->phone);
        $this->assertSame(WhatsappDevice::STATUS_CONNECTED, $device->status);
        $this->assertSame('token-rahasia-abc', $device->token);

        $raw = DB::table('whatsapp_devices')->where('id', $device->id)->value('token');
        $this->assertNotSame('token-rahasia-abc', $raw);
        $this->assertStringNotContainsString('token-rahasia-abc', (string) $raw);
    }

    public function test_store_fonnte_device_with_bad_token_is_rejected(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'invalid token'], 400),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.store'), [
                'provider' => 'fonnte',
                'token' => 'token-salah',
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, WhatsappDevice::count());
    }

    public function test_login_page_is_only_for_gowa_devices(): void
    {
        $fonnte = WhatsappDevice::create(['provider' => 'fonnte', 'token' => 'abc']);

        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.devices.login', $fonnte))
            ->assertRedirect(route('admin.whatsapp.index'))
            ->assertSessionHas('error');
    }

    public function test_destroy_gowa_device_removes_slot_and_row(): void
    {
        $device = WhatsappDevice::create(['provider' => 'gowa', 'device_id' => 'yayasan']);
        $this->fakeGowaOk();

        $this->actingAs($this->admin())
            ->delete(route('admin.whatsapp.devices.destroy', $device))
            ->assertSessionHas('success');

        $this->assertSame(0, WhatsappDevice::count());
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'http://gowa.test/devices/yayasan');
    }

    public function test_test_send_endpoint_sends_and_logs(): void
    {
        $device = WhatsappDevice::create(['provider' => 'gowa', 'device_id' => 'yayasan']);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/app/status')) {
                return Http::response([
                    'status' => 200, 'code' => 'SUCCESS',
                    'results' => ['is_connected' => true, 'is_logged_in' => true, 'jid' => '628987654321@s.whatsapp.net'],
                ]);
            }

            return Http::response(['status' => 200, 'code' => 'SUCCESS', 'results' => ['id' => 'MSG-9']]);
        });

        $this->actingAs($this->admin())
            ->post(route('admin.whatsapp.devices.test', $device), [
                'phone' => '081234567890',
                'message' => 'Pesan uji coba',
            ])
            ->assertSessionHas('success');

        $log = WhatsappMessageLog::sole();
        $this->assertSame(WhatsappMessageLog::TYPE_TEST, $log->type);
        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertSame('MSG-9', $log->provider_message_id);
    }
}
