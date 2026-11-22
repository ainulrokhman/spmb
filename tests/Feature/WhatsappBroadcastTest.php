<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappBroadcastStep;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\SchoolUnit;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use App\Services\Whatsapp\WhatsappBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * WhatsApp broadcast: admin-composed bulk messages combining
 * the Al-Hikmah durable recipient tracking (pause / resume /
 * cancel) with the psb-laravel random delay range and
 * payment-status targeting.
 */
class WhatsappBroadcastTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $officer;

    private SchoolUnit $unit;

    private AdmissionWave $wave;

    private AdmissionTrack $track;

    /** @var array<int, array{phone: ?string, message: string}> */
    private array $sent = [];

    private bool $failMarkedMessages = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->officer = $this->superAdmin();

        config([
            'whatsapp.gowa.base_url' => 'http://gowa.test',
            'whatsapp.gowa.username' => 'admin',
            'whatsapp.gowa.password' => 'rahasia',
            'whatsapp.fonnte.base_url' => 'https://api.fonnte.com',
        ]);

        $this->unit = SchoolUnit::factory()->create(['name' => 'SMP Broadcast', 'code' => '41', 'form_fee' => 100000]);
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $this->wave = AdmissionWave::factory()->create([
            'school_unit_id' => $this->unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Gelombang 1',
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->addDays(20),
        ]);
        $this->track = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id, 'name' => 'Reguler']);
        $this->wave->admissionTracks()->attach($this->track);

        WhatsappDevice::create([
            'school_unit_id' => $this->unit->id,
            'provider' => WhatsappDevice::PROVIDER_GOWA,
            'device_id' => 'unit-1',
        ]);

        $this->sent = [];
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/app/status')) {
                return Http::response([
                    'status' => 200,
                    'code' => 'SUCCESS',
                    'results' => ['is_connected' => true, 'is_logged_in' => true, 'jid' => '628987654321@s.whatsapp.net'],
                ]);
            }

            if (str_ends_with($request->url(), '/send/message')) {
                if ($this->failMarkedMessages && str_contains($request['message'] ?? '', 'GAGAL')) {
                    return Http::response(['status' => 500, 'code' => 'ERROR', 'message' => 'boom'], 500);
                }
                $this->sent[] = ['phone' => $request['phone_number'] ?? null, 'message' => $request['message'] ?? ''];

                return Http::response(['status' => 200, 'code' => 'SUCCESS', 'results' => ['id' => 'msg-1']]);
            }

            return Http::response([], 404);
        });
    }

    private function makeApplication(string $child, string $phone, array $overrides = []): Application
    {
        $guardian = User::factory()->create(['name' => 'Wali '.$child, 'phone' => $phone]);
        $guardian->assignRole('Wali');

        return Application::factory()->create(array_merge([
            'user_id' => $guardian->id,
            'school_unit_id' => $this->unit->id,
            'admission_wave_id' => $this->wave->id,
            'admission_track_id' => $this->track->id,
            'child_name' => $child,
            'status' => 'submitted',
            'form_fee_amount' => 100000,
            'form_fee_status' => 'unpaid',
        ], $overrides));
    }

    private function service(): WhatsappBroadcastService
    {
        return app(WhatsappBroadcastService::class);
    }

    /** @return array<string, mixed> */
    private function broadcastData(array $overrides = []): array
    {
        return array_merge([
            'school_unit_id' => $this->unit->id,
            'message' => 'Halo {nama_wali}, info untuk {nama_anak}.',
            'delay_min' => 0,
            'delay_max' => 0,
        ], $overrides);
    }

    public function test_recipient_resolution_filters(): void
    {
        $a = $this->makeApplication('Anak A', '081200000001');
        $b = $this->makeApplication('Anak B', '081200000002', ['status' => 'verified', 'form_fee_status' => 'paid']);
        $c = $this->makeApplication('Anak C', '081200000003', ['form_fee_status' => 'paid']);
        $this->makeApplication('Anak Tanpa Nomor', '');

        Invoice::factory()->create(['application_id' => $b->id, 'status' => 'unpaid', 'total' => 500000]);
        Invoice::factory()->create(['application_id' => $c->id, 'status' => 'paid', 'total' => 500000]);

        $service = $this->service();
        $ids = fn ($filters, $accessible = null) => $service->resolveApplications($filters, $accessible)->pluck('id')->all();

        // Unit only: everyone with a phone (the phoneless guardian is out).
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $ids(['school_unit_id' => $this->unit->id]));
        // Application status.
        $this->assertSame([$b->id], $ids(['school_unit_id' => $this->unit->id, 'application_status' => 'verified']));
        // Form fee payment filters.
        $this->assertSame([$a->id], $ids(['school_unit_id' => $this->unit->id, 'payment_filter' => 'formulir_belum_lunas']));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $ids(['school_unit_id' => $this->unit->id, 'payment_filter' => 'formulir_lunas']));
        // Re-registration invoice payment filters.
        $this->assertSame([$b->id], $ids(['school_unit_id' => $this->unit->id, 'payment_filter' => 'tagihan_belum_lunas']));
        $this->assertSame([$c->id], $ids(['school_unit_id' => $this->unit->id, 'payment_filter' => 'tagihan_lunas']));
        // Unit scoping: a caller restricted to other units gets nobody.
        $this->assertSame([], $ids(['school_unit_id' => $this->unit->id], [999]));
    }

    public function test_message_rendering_variables_and_spintax(): void
    {
        $application = $this->makeApplication('Anak Render', '081200000010');

        $rendered = $this->service()->renderMessage(
            '{Halo|Salam} {nama_wali}, {nama_anak} terdaftar di {unit} {gelombang}.',
            $application,
        );

        $this->assertMatchesRegularExpression(
            '/^(Halo|Salam) Wali Anak Render, Anak Render terdaftar di SMP Broadcast Gelombang 1\.$/',
            $rendered,
        );
        $this->assertStringNotContainsString('|', $rendered);
    }

    public function test_create_broadcast_snapshots_recipients_and_manual_pick_narrows(): void
    {
        $a = $this->makeApplication('Anak A', '081200000001');
        $b = $this->makeApplication('Anak B', '081200000002');

        $broadcast = $this->service()->createBroadcast(
            $this->broadcastData(['application_ids' => [$b->id]]),
            $this->officer,
        );

        $this->assertSame(WhatsappBroadcast::STATUS_PENDING, $broadcast->status);
        $this->assertSame(1, $broadcast->total_recipients);
        $recipient = $broadcast->recipients()->sole();
        $this->assertSame($b->id, $recipient->application_id);
        $this->assertSame('081200000002', $recipient->phone);
        $this->assertSame('Anak B', $recipient->child_name);

        // A manual pick outside the filtered unit never widens the set.
        $otherUnit = SchoolUnit::factory()->create(['name' => 'Unit Lain', 'code' => '42']);
        $outsider = $this->makeApplication('Anak Luar', '081200000009', ['school_unit_id' => $otherUnit->id]);
        $second = $this->service()->createBroadcast(
            $this->broadcastData(['application_ids' => [$a->id, $outsider->id]]),
            $this->officer,
        );
        $this->assertSame([$a->id], $second->recipients()->pluck('application_id')->all());
    }

    public function test_sending_chain_sends_all_and_completes(): void
    {
        $this->makeApplication('Anak A', '081200000001');
        $this->makeApplication('Anak B', '081200000002');

        $broadcast = $this->service()->createBroadcast($this->broadcastData(), $this->officer);
        $this->service()->start($broadcast);

        $broadcast->refresh();
        $this->assertSame(WhatsappBroadcast::STATUS_COMPLETED, $broadcast->status);
        $this->assertSame(2, $broadcast->sent_count);
        $this->assertSame(0, $broadcast->failed_count);
        $this->assertNotNull($broadcast->finished_at);
        $this->assertSame(2, WhatsappBroadcastRecipient::where('status', 'sent')->count());
        $this->assertCount(2, $this->sent);
        $this->assertStringContainsString('Halo Wali Anak A, info untuk Anak A.', $this->sent[0]['message']);
        $this->assertSame(2, WhatsappMessageLog::where('type', WhatsappMessageLog::TYPE_MANUAL)->where('status', 'sent')->count());
    }

    public function test_pause_stops_chain_and_resume_continues(): void
    {
        Queue::fake();

        $this->makeApplication('Anak A', '081200000001');
        $this->makeApplication('Anak B', '081200000002');

        $service = $this->service();
        $broadcast = $service->createBroadcast($this->broadcastData(), $this->officer);
        $service->start($broadcast);
        Queue::assertPushed(SendWhatsappBroadcastStep::class);

        $step = new SendWhatsappBroadcastStep($broadcast->id);
        $step->handle($service);
        $this->assertSame(1, $broadcast->refresh()->sent_count);

        $service->pause($broadcast);
        $step->handle($service);
        $this->assertSame(1, $broadcast->refresh()->sent_count);
        $this->assertSame(WhatsappBroadcast::STATUS_PAUSED, $broadcast->status);

        $service->start($broadcast);
        $step->handle($service);
        $step->handle($service);
        $this->assertSame(WhatsappBroadcast::STATUS_COMPLETED, $broadcast->refresh()->status);
        $this->assertSame(2, $broadcast->sent_count);
    }

    public function test_cancel_stops_chain(): void
    {
        Queue::fake();

        $this->makeApplication('Anak A', '081200000001');

        $service = $this->service();
        $broadcast = $service->createBroadcast($this->broadcastData(), $this->officer);
        $service->start($broadcast);
        $service->cancel($broadcast);

        (new SendWhatsappBroadcastStep($broadcast->id))->handle($service);

        $this->assertSame(WhatsappBroadcast::STATUS_CANCELLED, $broadcast->refresh()->status);
        $this->assertSame(0, $broadcast->sent_count);
        $this->assertCount(0, $this->sent);
    }

    public function test_failed_recipient_does_not_stop_chain(): void
    {
        $this->failMarkedMessages = true;

        $this->makeApplication('GAGAL Anak', '081200000001');
        $this->makeApplication('Anak B', '081200000002');

        $broadcast = $this->service()->createBroadcast(
            $this->broadcastData(['message' => 'Info untuk {nama_anak}']),
            $this->officer,
        );
        $this->service()->start($broadcast);

        $broadcast->refresh();
        $this->assertSame(WhatsappBroadcast::STATUS_COMPLETED, $broadcast->status);
        $this->assertSame(1, $broadcast->sent_count);
        $this->assertSame(1, $broadcast->failed_count);
        $failed = WhatsappBroadcastRecipient::where('status', 'failed')->sole();
        $this->assertSame('GAGAL Anak', $failed->child_name);
        $this->assertNotNull($failed->error);
    }

    public function test_admin_pages_store_and_preview(): void
    {
        $this->makeApplication('Anak A', '081200000001');

        $this->actingAs($this->officer)->get(route('admin.whatsapp.broadcasts.index'))->assertOk();
        $this->actingAs($this->officer)->get(route('admin.whatsapp.broadcasts.create'))->assertOk();

        $this->actingAs($this->officer)
            ->postJson(route('admin.whatsapp.broadcasts.preview'), ['school_unit_id' => $this->unit->id])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('recipients.0.child_name', 'Anak A');

        $response = $this->actingAs($this->officer)->post(route('admin.whatsapp.broadcasts.store'), [
            'school_unit_id' => $this->unit->id,
            'message' => 'Pengumuman untuk {nama_anak}',
            'delay_min' => 5,
            'delay_max' => 15,
            'confirm' => '1',
        ]);
        $broadcast = WhatsappBroadcast::sole();
        $response->assertRedirect(route('admin.whatsapp.broadcasts.show', $broadcast));
        $this->assertSame(1, $broadcast->total_recipients);

        $this->actingAs($this->officer)->get(route('admin.whatsapp.broadcasts.show', $broadcast))->assertOk();

        Queue::fake();
        $this->actingAs($this->officer)
            ->post(route('admin.whatsapp.broadcasts.start', $broadcast))
            ->assertRedirect();
        $this->assertSame(WhatsappBroadcast::STATUS_SENDING, $broadcast->refresh()->status);
        Queue::assertPushed(SendWhatsappBroadcastStep::class);
    }

    public function test_broadcast_pages_require_whatsapp_permission(): void
    {
        $guardian = User::factory()->create(['phone' => '081200000099']);
        $guardian->assignRole('Wali');

        $this->actingAs($guardian)->get(route('admin.whatsapp.broadcasts.index'))->assertForbidden();
        $this->actingAs($guardian)->get(route('admin.whatsapp.broadcasts.create'))->assertForbidden();
    }
}
