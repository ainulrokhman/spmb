<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\SchoolUnit;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappOtp;
use App\Services\Whatsapp\WhatsappOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class WhatsappOtpTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private SchoolUnit $unit;

    private AdmissionTrack $track;

    /** @var array<int, array{device: ?string, message: string}> */
    private array $sent = [];

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

        $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => true]);
        $this->unit = SchoolUnit::factory()->create(['code' => '31', 'form_fee' => 0]);
        $this->track = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id]);
        $wave = AdmissionWave::factory()->create(['school_unit_id' => $this->unit->id, 'academic_year_id' => $year->id]);
        $wave->admissionTracks()->attach($this->track);

        WhatsappDevice::create([
            'school_unit_id' => $this->unit->id,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'unit-1',
        ]);
        WhatsappDevice::create([
            'school_unit_id' => null,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'yayasan-1',
        ]);

        $this->sent = [];
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/app/status')) {
                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => [
                        'is_connected' => true,
                        'is_logged_in' => true,
                        'jid' => '628987654321@s.whatsapp.net',
                    ],
                ]);
            }

            if (str_ends_with($request->url(), '/send/message')) {
                $this->sent[] = [
                    'device' => $request->header('X-Device-Id')[0] ?? null,
                    'message' => $request['message'] ?? '',
                ];

                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => ['message_id' => 'msg-1'],
                ]);
            }

            return Http::response([], 404);
        });
    }

    private function enableOtp(): void
    {
        Setting::set('whatsapp_otp_enabled', '1');
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Wali Baru',
            'phone' => '081234567890',
            'password' => 'password-rahasia',
            'password_confirmation' => 'password-rahasia',
            'child_name' => 'Anak Pertama',
            'guardian_relation' => 'ayah',
            'school_unit_id' => $this->unit->id,
            'admission_track_id' => $this->track->id,
        ], $overrides);
    }

    /**
     * Ask the endpoint for a code and read the code back out
     * of the WhatsApp text that was actually sent.
     */
    private function requestCode(string $phone = '081234567890'): string
    {
        $this->postJson(route('guardian.register.otp'), [
            'phone' => $phone,
            'school_unit_id' => $this->unit->id,
            'name' => 'Wali Baru',
        ])->assertOk()->assertJson(['success' => true]);

        $message = end($this->sent)['message'];
        $this->assertMatchesRegularExpression('/\*(\d{6})\*/', $message);
        preg_match('/\*(\d{6})\*/', $message, $matches);

        return $matches[1];
    }

    public function test_send_endpoint_delivers_a_hashed_code_via_the_unit_device(): void
    {
        $this->enableOtp();

        $code = $this->requestCode();

        // The unit's own device sends first, not the foundation.
        $this->assertSame('unit-1', $this->sent[0]['device']);

        $otp = WhatsappOtp::firstOrFail();
        $this->assertSame('081234567890', $otp->phone);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertNull($otp->verified_at);

        // The report log must never contain the real code.
        $log = WhatsappMessageLog::where('type', WhatsappMessageLog::TYPE_OTP)->firstOrFail();
        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertStringNotContainsString($code, $log->message);
    }

    public function test_send_endpoint_respects_the_resend_cooldown(): void
    {
        $this->enableOtp();
        $this->requestCode();

        $this->postJson(route('guardian.register.otp'), [
            'phone' => '081234567890',
            'school_unit_id' => $this->unit->id,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(1, WhatsappOtp::count());
        $this->assertCount(1, $this->sent);

        $this->travel(61)->seconds();
        $this->requestCode('081234567890');
        $this->assertCount(2, $this->sent);
    }

    public function test_send_endpoint_is_closed_while_otp_is_disabled(): void
    {
        $this->postJson(route('guardian.register.otp'), [
            'phone' => '081234567890',
            'school_unit_id' => $this->unit->id,
        ])->assertStatus(422);

        $this->assertSame(0, WhatsappOtp::count());
    }

    public function test_send_endpoint_rejects_an_already_registered_phone(): void
    {
        $this->enableOtp();
        User::factory()->create(['phone' => '081234567890']);

        $this->postJson(route('guardian.register.otp'), [
            'phone' => '+62 812-3456-7890',
            'school_unit_id' => $this->unit->id,
        ])->assertStatus(422)->assertJsonPath('message', 'Nomor HP sudah terdaftar. Silakan masuk dengan nomor tersebut.');

        $this->assertSame(0, WhatsappOtp::count());
    }

    public function test_undeliverable_code_is_not_left_verifiable(): void
    {
        $this->enableOtp();
        WhatsappDevice::query()->delete();

        $this->postJson(route('guardian.register.otp'), [
            'phone' => '081234567890',
            'school_unit_id' => $this->unit->id,
        ])->assertStatus(422);

        $this->assertSame(0, WhatsappOtp::count());
    }

    public function test_verify_locks_after_five_wrong_attempts_and_expires(): void
    {
        $service = app(WhatsappOtpService::class);
        $otp = WhatsappOtp::create([
            'phone' => '081234567890',
            'purpose' => WhatsappOtp::PURPOSE_REGISTRATION,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->assertFalse($service->verify('081234567890', '999999'), "Percobaan {$attempt} harus gagal");
        }
        $this->assertSame(5, $otp->refresh()->attempts);
        $this->assertFalse($service->verify('081234567890', '123456'), 'Kode benar pun harus ditolak setelah terkunci');

        $otp->update(['attempts' => 0]);
        $this->travel(6)->minutes();
        $this->assertFalse($service->verify('081234567890', '123456'), 'Kode kedaluwarsa harus ditolak');
    }

    public function test_registration_requires_a_valid_code_while_otp_is_on(): void
    {
        $this->enableOtp();

        // No code at all.
        $this->post(route('guardian.register.store'), $this->registrationPayload())
            ->assertSessionHasErrors('otp_code');
        $this->assertNull(User::where('phone', '081234567890')->first());

        // Wrong code.
        $this->requestCode();
        $this->post(route('guardian.register.store'), $this->registrationPayload(['otp_code' => '000000']))
            ->assertSessionHasErrors('otp_code');
        $this->assertNull(User::where('phone', '081234567890')->first());
    }

    public function test_registration_with_the_sent_code_succeeds_and_consumes_it(): void
    {
        $this->enableOtp();
        $code = $this->requestCode();

        $response = $this->post(route('guardian.register.store'), $this->registrationPayload(['otp_code' => $code]));

        $user = User::where('phone', '081234567890')->first();
        $this->assertNotNull($user);
        $response->assertRedirect(route('guardian.applications.show', $user->applications()->firstOrFail()));
        $this->assertNotNull(WhatsappOtp::firstOrFail()->verified_at);

        // The same code cannot verify a second time.
        $this->assertFalse(app(WhatsappOtpService::class)->verify('081234567890', $code));
    }

    public function test_register_page_shows_the_otp_field_only_while_otp_is_on(): void
    {
        $this->get(route('guardian.register'))->assertDontSee('name="otp_code"', false);

        $this->enableOtp();
        $this->get(route('guardian.register'))
            ->assertSee('name="otp_code"', false)
            ->assertSee('Kirim Kode');
    }

    public function test_admin_can_toggle_otp_and_cleanup_removes_expired_codes(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.whatsapp.otp-toggle'))
            ->assertRedirect();
        $this->assertTrue(WhatsappOtpService::isRequired());

        WhatsappOtp::create([
            'phone' => '081234567890',
            'purpose' => WhatsappOtp::PURPOSE_REGISTRATION,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);
        WhatsappOtp::create([
            'phone' => '081234567891',
            'purpose' => WhatsappOtp::PURPOSE_REGISTRATION,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->artisan('whatsapp:cleanup-otps')->assertSuccessful();
        $this->assertSame(1, WhatsappOtp::count());
        $this->assertSame('081234567891', WhatsappOtp::firstOrFail()->phone);
    }
}
