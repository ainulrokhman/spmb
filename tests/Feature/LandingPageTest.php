<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\GalleryPhoto;
use App\Models\SchoolUnit;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public landing page at /: foundation branding, the
 * gallery slide, and the units currently open for
 * registration (shared with the info page).
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private SchoolUnit $openUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);

        $this->openUnit = SchoolUnit::factory()->create([
            'name' => 'SMP Landing',
            'code' => '71',
            'address' => 'Jl. Pendidikan No. 1, Yogyakarta',
            'phone' => '081234567890',
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $this->openUnit->id]);
        AdmissionWave::factory()->create([
            'school_unit_id' => $this->openUnit->id,
            'academic_year_id' => $year->id,
            'name' => 'Gelombang Landing',
            'opens_at' => now()->subDays(2),
            'closes_at' => now()->addDays(12),
        ]);

        $closedUnit = SchoolUnit::factory()->create(['name' => 'SMA Tertutup', 'code' => '72']);
        AdmissionWave::factory()->create([
            'school_unit_id' => $closedUnit->id,
            'academic_year_id' => $year->id,
            'name' => 'Gelombang Lalu',
            'opens_at' => now()->subDays(40),
            'closes_at' => now()->subDays(10),
        ]);
    }

    public function test_landing_shows_open_units_with_countdown_and_contact_links(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('SMP Landing');
        $response->assertSee('Gelombang Landing');
        $response->assertSee('Ditutup 12 hari lagi');
        $response->assertSee('https://wa.me/6281234567890');
        $response->assertSee('google.com/maps');
        $response->assertDontSee('SMA Tertutup');
        // Static sections.
        $response->assertSee('Alur Pendaftaran');
        $response->assertSee('Pertanyaan yang Sering Diajukan');
    }

    public function test_landing_uses_branding_settings_and_logo(): void
    {
        Setting::set('foundation_name', 'Yayasan Uji Coba');
        Setting::set('foundation_tagline', 'Tagline yayasan uji.');
        Setting::set('site_logo', 'branding/logo-uji.png');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Yayasan Uji Coba');
        $response->assertSee('Tagline yayasan uji.');
        $response->assertSee('branding/logo-uji.png');
        $response->assertSee('og:image', false);
    }

    public function test_gallery_carousel_only_renders_with_active_photos(): void
    {
        $this->get('/')->assertOk()->assertDontSee('galeriSlide');

        GalleryPhoto::factory()->create(['caption' => 'Kegiatan Uji', 'is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('Kegiatan Uji');

        GalleryPhoto::factory()->create(['caption' => 'Kegiatan Uji', 'is_active' => true]);
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('galeriSlide');
        $response->assertSee('Kegiatan Uji');
    }

    public function test_profile_video_embeds_when_the_setting_is_a_youtube_url(): void
    {
        $this->get('/')->assertOk()->assertDontSee('youtube.com/embed');

        Setting::set('profile_video_url', 'https://www.youtube.com/watch?v=abc123XYZ');

        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/abc123XYZ');
    }

    public function test_info_page_still_works(): void
    {
        $this->get(route('admissions.info'))
            ->assertOk()
            ->assertSee('SMP Landing');
    }
}
