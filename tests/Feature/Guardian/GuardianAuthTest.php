<?php

namespace Tests\Feature\Guardian;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class GuardianAuthTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    /**
     * Create a guardian account directly.
     */
    private function guardian(string $phone = '081234567890'): User
    {
        $user = User::factory()->create(['phone' => $phone]);
        $user->assignRole('Wali');

        return $user;
    }

    /**
     * A unit with one open wave and one track, for registration
     * payloads that also start an application.
     *
     * @return array{unit: SchoolUnit, wave: AdmissionWave, track: AdmissionTrack}
     */
    private function scenario(): array
    {
        $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => true]);
        $unit = SchoolUnit::factory()->create(['code' => '31', 'form_fee' => 0]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create(['school_unit_id' => $unit->id, 'academic_year_id' => $year->id]);
        $wave->admissionTracks()->attach($track);

        return ['unit' => $unit, 'wave' => $wave, 'track' => $track];
    }

    private function registrationPayload(array $scenario, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Wali Baru',
            'phone' => '+62 812-3456-7890',
            'password' => 'password-rahasia',
            'password_confirmation' => 'password-rahasia',
            'child_name' => 'Anak Pertama',
            'guardian_relation' => 'ayah',
            'school_unit_id' => $scenario['unit']->id,
            'admission_track_id' => $scenario['track']->id,
        ], $overrides);
    }

    public function test_register_page_is_a_single_form_with_all_fields(): void
    {
        // Regression: the registration page must be ONE POST form —
        // splitting the visible fields (a GET cascade form) from
        // the submitting form silently dropped the typed data.
        $scenario = $this->scenario();

        $response = $this->get(route('guardian.register'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<form'));
        $response->assertSee('name="child_name"', false);
        $response->assertSee('name="guardian_relation"', false);
        $response->assertSee('name="school_unit_id"', false);
        $response->assertSee('name="admission_track_id"', false);
        $response->assertDontSee('type="hidden" name="child_name"', false);
    }

    public function test_guardian_can_register_with_phone_number(): void
    {
        $scenario = $this->scenario();

        $response = $this->post(route('guardian.register.store'), $this->registrationPayload($scenario));

        $user = User::where('phone', '081234567890')->first();
        $this->assertNotNull($user, 'Nomor HP harus dinormalisasi ke bentuk 08…');
        $this->assertTrue($user->hasRole('Wali'));
        $this->assertAuthenticatedAs($user);

        // Registration also starts the child's application.
        $application = Application::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Anak Pertama', $application->child_name);
        $this->assertSame('ayah', $application->guardian_relation);
        $response->assertRedirect(route('guardian.applications.show', $application));
    }

    public function test_guardian_registration_rejects_duplicate_phone(): void
    {
        $this->guardian();
        $scenario = $this->scenario();

        $response = $this->post(route('guardian.register.store'), $this->registrationPayload($scenario, [
            'name' => 'Wali Lain',
            'phone' => '6281234567890',
        ]));

        $response->assertSessionHasErrors('phone_normalized');
        $this->assertGuest();
        $this->assertSame(0, Application::count());
    }

    public function test_registration_with_multiple_open_waves_requires_a_wave_choice(): void
    {
        $scenario = $this->scenario();
        $secondWave = AdmissionWave::factory()->create([
            'school_unit_id' => $scenario['unit']->id,
            'academic_year_id' => $scenario['wave']->academic_year_id,
        ]);
        $secondWave->admissionTracks()->attach($scenario['track']);

        $response = $this->post(route('guardian.register.store'), $this->registrationPayload($scenario));

        $response->assertSessionHasErrors('admission_wave_id');
        $this->assertGuest();
        $this->assertNull(User::where('phone', '081234567890')->first());
    }

    public function test_guardian_can_log_in_with_phone_number(): void
    {
        $guardian = $this->guardian();

        $response = $this->post('/login', [
            'login' => '6281234567890',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('guardian.dashboard'));
        $this->assertAuthenticatedAs($guardian);
    }

    public function test_guardian_cannot_log_in_with_wrong_password(): void
    {
        $this->guardian();

        $response = $this->post('/login', [
            'login' => '081234567890',
            'password' => 'salah-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_staff_login_with_email_still_works(): void
    {
        $response = $this->post('/login', [
            'login' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->admin());
    }

    public function test_guardian_cannot_access_admin_area(): void
    {
        $response = $this->actingAs($this->guardian())->get('/admin');

        $response->assertForbidden();
    }

    public function test_staff_cannot_access_guardian_area(): void
    {
        $response = $this->actingAs($this->admin())->get(route('guardian.dashboard'));

        $response->assertForbidden();
    }

    public function test_public_info_page_lists_units_with_open_waves(): void
    {
        $unit = \App\Models\SchoolUnit::factory()->create(['name' => 'SMP Terbuka']);
        $wave = \App\Models\AdmissionWave::factory()->create(['school_unit_id' => $unit->id]);

        $response = $this->get(route('admissions.info'));

        $response->assertOk();
        $response->assertSee('SMP Terbuka');
        $response->assertSee($wave->name);
    }
}
