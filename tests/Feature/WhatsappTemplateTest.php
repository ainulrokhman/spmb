<?php

namespace Tests\Feature;

use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappMessageTemplate;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class WhatsappTemplateTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

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
    }

    /**
     * A connected foundation GOWA device; /send/message bodies
     * are recorded into $sent so tests can assert on the exact
     * text that left the building.
     *
     * @param  array<int, string>  $sent
     */
    private function fakeGowaWithFoundationDevice(array &$sent): void
    {
        WhatsappDevice::create([
            'school_unit_id' => null,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'yayasan-1',
        ]);

        Http::fake(function (Request $request) use (&$sent) {
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
                $sent[] = $request['message'] ?? '';

                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => ['message_id' => 'msg-1'],
                ]);
            }

            return Http::response([], 404);
        });
    }

    public function test_template_pages_require_the_whatsapp_permission(): void
    {
        $this->get(route('admin.whatsapp.templates.index'))->assertRedirect(route('login'));

        $this->actingAs($this->regularUser())
            ->get(route('admin.whatsapp.templates.index'))
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.whatsapp.templates.index'))
            ->assertOk()
            ->assertSee('Kode OTP Pendaftaran')
            ->assertSee('Pengingat Tagihan');
    }

    public function test_admin_can_edit_and_deactivate_a_template(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('admin.whatsapp.templates.update', WhatsappMessageLog::TYPE_REMINDER), [
                'content' => 'Halo {nama_wali}, tagihan {jenis} ananda {nama_anak} sebesar {nominal}.',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.whatsapp.templates.index'));

        $template = WhatsappMessageTemplate::where('type', WhatsappMessageLog::TYPE_REMINDER)->firstOrFail();
        $this->assertStringContainsString('Halo {nama_wali}', $template->content);
        $this->assertFalse($template->is_active);
        $this->assertNull(WhatsappMessageTemplate::activeFor(WhatsappMessageLog::TYPE_REMINDER));
    }

    public function test_restore_brings_back_the_default_text(): void
    {
        WhatsappMessageTemplate::create([
            'type' => WhatsappMessageLog::TYPE_PAYMENT,
            'content' => 'teks yang sudah diubah admin',
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.whatsapp.templates.restore', WhatsappMessageLog::TYPE_PAYMENT))
            ->assertRedirect();

        $this->assertSame(
            WhatsappMessageTemplate::defaultFor(WhatsappMessageLog::TYPE_PAYMENT),
            WhatsappMessageTemplate::where('type', WhatsappMessageLog::TYPE_PAYMENT)->value('content'),
        );
    }

    public function test_unknown_template_type_is_a_404(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.whatsapp.templates.edit', 'jenis-ngawur'))
            ->assertNotFound();
    }

    public function test_send_templated_renders_variables_and_logs_the_final_text(): void
    {
        $sent = [];
        $this->fakeGowaWithFoundationDevice($sent);

        $log = app(WhatsappSender::class)->sendTemplated(null, '081234567890', WhatsappMessageLog::TYPE_REMINDER, [
            'nama_wali' => 'Ibu Sari',
            'nama_anak' => 'Ahmad Fauzi',
            'jenis' => 'Daftar Ulang',
            'nominal' => 'Rp 1.500.000',
            'jatuh_tempo' => '20 Desember 2026',
        ]);

        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Ahmad Fauzi', $sent[0]);
        $this->assertStringContainsString('Rp 1.500.000', $sent[0]);
        $this->assertStringNotContainsString('{nama_anak}', $sent[0]);
        $this->assertSame($sent[0], $log->message);
    }

    public function test_inactive_template_suppresses_the_send_but_still_logs_it(): void
    {
        $sent = [];
        $this->fakeGowaWithFoundationDevice($sent);

        WhatsappMessageTemplate::create([
            'type' => WhatsappMessageLog::TYPE_RESULT,
            'content' => 'Hasil: {hasil}',
            'is_active' => false,
        ]);

        $log = app(WhatsappSender::class)->sendTemplated(null, '081234567890', WhatsappMessageLog::TYPE_RESULT, [
            'hasil' => 'Diterima',
        ]);

        $this->assertSame(WhatsappMessageLog::STATUS_SKIPPED, $log->status);
        $this->assertStringContainsString('dinonaktifkan', $log->error);
        $this->assertCount(0, $sent);
    }

    public function test_otp_text_is_logged_with_the_code_masked(): void
    {
        $sent = [];
        $this->fakeGowaWithFoundationDevice($sent);

        $log = app(WhatsappSender::class)->sendTemplated(null, '081234567890', WhatsappMessageLog::TYPE_OTP, [
            'nama' => 'Ibu Sari',
            'kode_otp' => '482913',
            'berlaku_menit' => 5,
        ]);

        $this->assertSame(WhatsappMessageLog::STATUS_SENT, $log->status);
        $this->assertStringContainsString('482913', $sent[0]);
        $this->assertStringNotContainsString('482913', $log->message);
        $this->assertStringContainsString('••••••', $log->message);
    }

    public function test_admin_test_send_uses_the_sample_data(): void
    {
        $sent = [];
        $this->fakeGowaWithFoundationDevice($sent);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.whatsapp.templates.test', WhatsappMessageLog::TYPE_OTP), [
                'phone' => '081234567890',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertCount(1, $sent);
        $this->assertStringContainsString('123456', $sent[0]);
    }
}
