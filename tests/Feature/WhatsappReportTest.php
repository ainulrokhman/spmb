<?php

namespace Tests\Feature;

use App\Models\SchoolUnit;
use App\Models\User;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class WhatsappReportTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private SchoolUnit $unit;

    private WhatsappDevice $unitDevice;

    private WhatsappDevice $foundationDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->unit = SchoolUnit::factory()->create(['code' => '33', 'name' => 'SMP Uji']);
        $this->unitDevice = WhatsappDevice::create([
            'school_unit_id' => $this->unit->id,
            'provider' => 'gowa',
            'device_id' => 'unit-33',
            'display_name' => 'WA SMP',
        ]);
        $this->foundationDevice = WhatsappDevice::create([
            'provider' => 'fonnte',
            'token' => 'token-yayasan',
            'display_name' => 'WA Yayasan',
        ]);
    }

    private function log(array $attributes = []): WhatsappMessageLog
    {
        return WhatsappMessageLog::create(array_merge([
            'whatsapp_device_id' => $this->unitDevice->id,
            'school_unit_id' => $this->unit->id,
            'recipient_phone' => '6281234567890',
            'recipient_name' => 'Wali A',
            'type' => WhatsappMessageLog::TYPE_PAYMENT,
            'message' => 'Pembayaran diterima',
            'status' => WhatsappMessageLog::STATUS_SENT,
        ], $attributes));
    }

    public function test_report_is_forbidden_without_the_permission(): void
    {
        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');

        $this->actingAs($panitia)->get(route('admin.whatsapp.report'))->assertForbidden();
        $this->actingAs($this->regularUser())->get(route('admin.whatsapp.report'))->assertForbidden();
    }

    public function test_report_shows_logs_and_summary_including_foundation_fallback(): void
    {
        $this->log();
        $this->log(['status' => WhatsappMessageLog::STATUS_FAILED, 'error' => 'provider menolak']);
        $this->log(['whatsapp_device_id' => $this->foundationDevice->id]); // sent via fallback
        $this->log(['whatsapp_device_id' => null, 'school_unit_id' => null, 'status' => WhatsappMessageLog::STATUS_SKIPPED]);

        $response = $this->actingAs($this->admin())->get(route('admin.whatsapp.report'));

        $response->assertOk()
            ->assertSee('Wali A')
            ->assertSee('via cadangan')
            ->assertSee('provider menolak');

        $summary = $response->viewData('summary');
        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['sent']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['via_foundation']);
    }

    public function test_report_filters_by_status_unit_and_search(): void
    {
        $this->log(['recipient_name' => 'Wali Terkirim']);
        $this->log(['recipient_name' => 'Wali Gagal', 'status' => WhatsappMessageLog::STATUS_FAILED]);
        $this->log([
            'recipient_name' => 'Wali Yayasan',
            'whatsapp_device_id' => $this->foundationDevice->id,
            'school_unit_id' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.report', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('Wali Gagal')
            ->assertDontSee('Wali Terkirim');

        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.report', ['school_unit_id' => 'yayasan']))
            ->assertOk()
            ->assertSee('Wali Yayasan')
            ->assertDontSee('Wali Terkirim');

        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.report', ['q' => 'Terkirim']))
            ->assertOk()
            ->assertSee('Wali Terkirim')
            ->assertDontSee('Wali Gagal');
    }

    public function test_report_export_downloads_csv_with_matching_rows(): void
    {
        $this->log(['recipient_name' => 'Wali CSV']);
        $this->log(['recipient_name' => 'Wali Lain', 'status' => WhatsappMessageLog::STATUS_FAILED]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.report.export', ['status' => 'sent']));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Wali CSV', $content);
        $this->assertStringNotContainsString('Wali Lain', $content);
        $this->assertStringContainsString('Device Pengirim', $content);
    }
}
