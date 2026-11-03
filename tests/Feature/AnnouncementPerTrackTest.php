<?php

namespace Tests\Feature;

use App\Contracts\InvoiceServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Result announcements can be scheduled per admission track
 * (wave–track pivot time) with the wave's global time as the
 * fallback: effective time = per-track when filled, else global.
 */
class AnnouncementPerTrackTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081234567890']);
        $this->guardian->assignRole('Wali');
    }

    /**
     * One unit, two tracks (A and B) opened by one wave, and one
     * passed application per track for the same guardian.
     *
     * @return array{wave: AdmissionWave, trackA: AdmissionTrack, trackB: AdmissionTrack, appA: Application, appB: Application}
     */
    private function scenario(?string $global = 'future'): array
    {
        $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => true]);
        $unit = SchoolUnit::factory()->create(['code' => '31']);
        $trackA = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id, 'name' => 'Jalur A']);
        $trackB = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id, 'name' => 'Jalur B']);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'announcement_at' => match ($global) {
                'past' => now()->subHour(),
                'future' => now()->addDay(),
                default => null,
            },
        ]);
        $wave->admissionTracks()->attach([$trackA->id, $trackB->id]);

        $make = fn (AdmissionTrack $track, string $child, string $number) => Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $year->id,
            'child_name' => $child,
            'gender' => 'L',
            'status' => 'passed',
            'registration_number' => $number,
            'submitted_at' => now(),
        ]);

        return [
            'wave' => $wave,
            'trackA' => $trackA,
            'trackB' => $trackB,
            'appA' => $make($trackA, 'Anak Jalur A', '3126001'),
            'appB' => $make($trackB, 'Anak Jalur B', '3126002'),
        ];
    }

    private function setTrackTime(AdmissionWave $wave, AdmissionTrack $track, $time): void
    {
        $wave->admissionTracks()->updateExistingPivot($track->id, ['announcement_at' => $time]);
    }

    public function test_per_track_time_overrides_a_later_global_time(): void
    {
        ['wave' => $wave, 'trackA' => $trackA, 'appA' => $appA, 'appB' => $appB] = $this->scenario('future');
        $pivotTime = now()->subMinutes(30);
        $this->setTrackTime($wave, $trackA, $pivotTime);

        $this->assertTrue($appA->fresh()->resultsAnnounced());
        $this->assertSame($pivotTime->format('Y-m-d H:i:s'), $appA->fresh()->announcementAt()->format('Y-m-d H:i:s'));

        // Track B has no pivot time: it follows the future global.
        $this->assertFalse($appB->fresh()->resultsAnnounced());
        $this->assertTrue($appB->fresh()->announcementAt()->eq($wave->announcement_at));
    }

    public function test_per_track_time_can_delay_beyond_an_earlier_global_time(): void
    {
        ['wave' => $wave, 'trackB' => $trackB, 'appA' => $appA, 'appB' => $appB] = $this->scenario('past');
        $this->setTrackTime($wave, $trackB, now()->addDays(2));

        $this->assertTrue($appA->fresh()->resultsAnnounced());
        $this->assertFalse($appB->fresh()->resultsAnnounced());
    }

    public function test_no_times_at_all_means_not_announced(): void
    {
        ['appA' => $appA] = $this->scenario(null);

        $this->assertNull($appA->fresh()->announcementAt());
        $this->assertFalse($appA->fresh()->resultsAnnounced());
    }

    public function test_guardian_dashboard_shows_one_track_result_and_hides_the_other(): void
    {
        ['wave' => $wave, 'trackA' => $trackA] = $this->scenario('future');
        $this->setTrackTime($wave, $trackA, now()->subHour());

        $this->actingAs($this->guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Lulus')
            ->assertSee('Menunggu Pengumuman');
    }

    public function test_invoice_page_and_creation_follow_the_track_time(): void
    {
        ['wave' => $wave, 'trackA' => $trackA, 'appA' => $appA, 'appB' => $appB] = $this->scenario('future');
        $this->setTrackTime($wave, $trackA, now()->subHour());

        $spi = FeeComponent::factory()->create(['code' => 'SPIU', 'name' => 'SPI']);
        $fee = Fee::factory()->create(['school_unit_id' => $appA->school_unit_id, 'fee_component_id' => $spi->id, 'mode' => 'fixed']);
        FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => 1000000]);

        $invoices = app(InvoiceServiceInterface::class);

        // Track B (not yet announced): invoice creation is refused.
        try {
            $invoices->createInvoice($appB->fresh(), []);
            $this->fail('Tagihan jalur B seharusnya belum dapat dibuat.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        // Track A (announced): the invoice can be created.
        $invoice = $invoices->createInvoice($appA->fresh(), []);
        $this->assertNotNull($invoice);

        // And the guardian of track B is turned away from the page.
        $this->actingAs($this->guardian)
            ->get(route('guardian.invoices.show', $appB))
            ->assertRedirect(route('guardian.dashboard'))
            ->assertSessionHas('error', 'Hasil pendaftaran belum diumumkan untuk gelombang ini.');
    }

    public function test_admin_wave_update_saves_and_clears_per_track_times(): void
    {
        ['wave' => $wave, 'trackA' => $trackA, 'trackB' => $trackB] = $this->scenario('future');
        $admin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $when = now()->addDays(3)->format('Y-m-d H:i:s');

        $payload = [
            'school_unit_id' => $wave->school_unit_id,
            'academic_year_id' => $wave->academic_year_id,
            'name' => $wave->name,
            'opens_at' => $wave->opens_at->format('Y-m-d H:i:s'),
            'closes_at' => $wave->closes_at->format('Y-m-d H:i:s'),
            'announcement_at' => $wave->announcement_at->format('Y-m-d H:i:s'),
            'tracks' => [$trackA->id, $trackB->id],
            'track_announcements' => [$trackA->id => $when],
            'is_active' => '1',
        ];

        $this->actingAs($admin)
            ->put(route('admin.admission-waves.update', $wave), $payload)
            ->assertRedirect(route('admin.admission-waves.index'));

        $pivotA = $wave->fresh()->admissionTracks->firstWhere('id', $trackA->id)->pivot->announcement_at;
        $pivotB = $wave->fresh()->admissionTracks->firstWhere('id', $trackB->id)->pivot->announcement_at;
        $this->assertSame($when, \Illuminate\Support\Carbon::parse($pivotA)->format('Y-m-d H:i:s'));
        $this->assertNull($pivotB);

        // Clearing the field returns the track to the global time.
        $payload['track_announcements'] = [$trackA->id => ''];
        $this->actingAs($admin)
            ->put(route('admin.admission-waves.update', $wave), $payload)
            ->assertRedirect(route('admin.admission-waves.index'));

        $this->assertNull($wave->fresh()->admissionTracks->firstWhere('id', $trackA->id)->pivot->announcement_at);
    }
}
