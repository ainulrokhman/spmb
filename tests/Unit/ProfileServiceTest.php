<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProfileService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default roles, permissions, and demo users
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->service = new ProfileService;
    }

    private function superAdmin(): User
    {
        return User::where('email', 'superadmin@example.com')->firstOrFail();
    }

    /**
     * Test updating a profile changes the name.
     */
    public function test_update_profile_changes_name(): void
    {
        $user = User::factory()->create();

        $updated = $this->service->updateProfile($user, ['name' => 'Brand New Name']);

        $this->assertSame('Brand New Name', $updated->name);
    }

    /**
     * Test a regular user can change their email address.
     */
    public function test_update_profile_changes_email_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->isProtectedSeedAccount($user));

        $updated = $this->service->updateProfile($user, [
            'name' => $user->name,
            'email' => 'changed@example.com',
        ]);

        $this->assertSame('changed@example.com', $updated->email);
    }

    /**
     * Test the protected seed account keeps its email while
     * other fields still update.
     */
    public function test_update_profile_keeps_seed_account_email(): void
    {
        $superAdmin = $this->superAdmin();

        $this->assertTrue($this->service->isProtectedSeedAccount($superAdmin));

        $updated = $this->service->updateProfile($superAdmin, [
            'name' => 'Renamed Admin',
            'email' => 'attacker@example.com',
        ]);

        $this->assertSame('Renamed Admin', $updated->name);
        $this->assertSame('superadmin@example.com', $updated->email);
    }

    /**
     * Test updating a profile keeps the password when none is given
     * and rehashes it when a new one is provided.
     */
    public function test_update_profile_password_handling(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->service->updateProfile($user, ['name' => $user->name]);
        $this->assertSame($originalHash, $user->fresh()->password);

        $this->service->updateProfile($user, [
            'name' => $user->name,
            'password' => 'another-new-secret',
        ]);
        $this->assertTrue(Hash::check('another-new-secret', $user->fresh()->password));
    }

    /**
     * Test uploading an avatar stores the file on the public disk.
     */
    public function test_update_profile_stores_uploaded_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $updated = $this->service->updateProfile($user, [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $this->assertNotNull($updated->avatar);
        Storage::disk('public')->assertExists($updated->avatar);
    }

    /**
     * Test replacing an avatar deletes the previous file.
     */
    public function test_update_profile_replacing_avatar_deletes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', 'old-image-contents');

        $user = User::factory()->create(['avatar' => 'avatars/old.jpg']);

        $updated = $this->service->updateProfile($user, [
            'name' => $user->name,
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ]);

        Storage::disk('public')->assertMissing('avatars/old.jpg');
        Storage::disk('public')->assertExists($updated->avatar);
    }
}
