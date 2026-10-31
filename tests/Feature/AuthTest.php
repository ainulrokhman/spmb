<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default roles, permissions, and demo users
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function regularUser(): User
    {
        return User::where('email', 'user@example.com')->firstOrFail();
    }

    /**
     * Test guests can view the login page.
     */
    public function test_guest_can_view_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertViewIs('auth.login');
    }

    /**
     * Test authenticated users are redirected away from the login page.
     */
    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $response = $this->actingAs($this->regularUser())->get('/login');

        $response->assertRedirect('/');
    }

    /**
     * Test a user can log in with valid credentials.
     */
    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = $this->regularUser();

        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $response->assertSessionHas('success');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Test a user cannot log in with a wrong password.
     */
    public function test_user_cannot_log_in_with_wrong_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test login requires a valid email and a password.
     */
    public function test_login_requires_a_valid_email_and_password(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['email', 'password']);

        $this->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Test logging in with remember me sets the recaller cookie.
     */
    public function test_user_can_log_in_with_remember_me(): void
    {
        $response = $this->post('/login', [
            'email' => 'user@example.com',
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertRedirect('/admin');
        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticated();
    }

    /**
     * Test an authenticated user can log out.
     */
    public function test_user_can_log_out(): void
    {
        $response = $this->actingAs($this->regularUser())->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Test guests cannot reach the logout endpoint.
     */
    public function test_guest_cannot_log_out(): void
    {
        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    /**
     * Test the login page shows the demo quick-login accounts.
     */
    public function test_login_page_shows_demo_quick_login_accounts(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('quickLogin');
        $response->assertSee('superadmin@example.com');
        $response->assertSee('admin@example.com');
        $response->assertSee('user@example.com');
    }
}
