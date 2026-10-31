<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Test guest is redirected to login from profile edit.
     */
    public function test_guest_is_redirected_to_login_from_profile(): void
    {
        $response = $this->get(route('admin.profile.edit'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test user can view profile edit page.
     */
    public function test_user_can_view_profile_edit_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.profile.edit'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.profile.edit');
        $response->assertViewHas('user');
    }

    /**
     * Test user can update basic profile details.
     */
    public function test_user_can_update_profile_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    /**
     * Test user can update password.
     */
    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /**
     * Test user can upload avatar.
     */
    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        
        $user = User::factory()->create();
        $avatar = UploadedFile::fake()->image('avatar.png');

        $response = $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $avatar,
            ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    /**
     * Test superadmin seed email cannot be updated.
     */
    public function test_superadmin_seed_email_cannot_be_updated(): void
    {
        $superadmin = User::where('email', 'superadmin@example.com')->first();

        $response = $this->actingAs($superadmin)
            ->put(route('admin.profile.update'), [
                'name' => 'Modified Superadmin Name',
                'email' => 'hacker@example.com', // Attempting to change email
            ]);

        $response->assertRedirect(route('admin.profile.edit'));
        $response->assertSessionHas('success');

        $superadmin->refresh();
        $this->assertEquals('Modified Superadmin Name', $superadmin->name);
        $this->assertEquals('superadmin@example.com', $superadmin->email); // Email must remain unchanged
    }
}
