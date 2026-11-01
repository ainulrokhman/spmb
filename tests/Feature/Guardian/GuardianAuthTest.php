<?php

namespace Tests\Feature\Guardian;

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

    public function test_guardian_can_register_with_phone_number(): void
    {
        $response = $this->post(route('guardian.register.store'), [
            'name' => 'Wali Baru',
            'phone' => '+62 812-3456-7890',
            'password' => 'password-rahasia',
            'password_confirmation' => 'password-rahasia',
        ]);

        $response->assertRedirect(route('guardian.dashboard'));
        $user = User::where('phone', '081234567890')->first();
        $this->assertNotNull($user, 'Nomor HP harus dinormalisasi ke bentuk 08…');
        $this->assertTrue($user->hasRole('Wali'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_guardian_registration_rejects_duplicate_phone(): void
    {
        $this->guardian();

        $response = $this->post(route('guardian.register.store'), [
            'name' => 'Wali Lain',
            'phone' => '6281234567890',
            'password' => 'password-rahasia',
            'password_confirmation' => 'password-rahasia',
        ]);

        $response->assertSessionHasErrors('phone_normalized');
        $this->assertGuest();
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
