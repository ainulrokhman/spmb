<?php

namespace Tests\Feature\Admin;

use App\Models\GalleryPhoto;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Admin gallery management (upload / edit / delete) and the
 * branding section of the settings page (foundation name,
 * tagline, optional logo, profile video URL).
 */
class GalleryManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        Storage::fake('public');
    }

    public function test_admin_can_upload_edit_and_delete_photos(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('admin.gallery.store'), [
                'photos' => [UploadedFile::fake()->image('kelas.jpg', 800, 600)],
                'caption' => 'Belajar di kelas',
            ])
            ->assertRedirect(route('admin.gallery.index'));

        $photo = GalleryPhoto::first();
        $this->assertNotNull($photo);
        Storage::disk('public')->assertExists($photo->file_path);

        $this->actingAs($admin)
            ->put(route('admin.gallery.update', $photo), [
                'caption' => 'Kegiatan olahraga',
                'sort_order' => 5,
            ])
            ->assertRedirect(route('admin.gallery.index'));

        $photo->refresh();
        $this->assertSame('Kegiatan olahraga', $photo->caption);
        $this->assertSame(5, $photo->sort_order);
        $this->assertFalse($photo->is_active);

        $path = $photo->file_path;
        $this->actingAs($admin)
            ->delete(route('admin.gallery.destroy', $photo))
            ->assertRedirect(route('admin.gallery.index'));

        $this->assertSame(0, GalleryPhoto::count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_officer_without_gallery_permission_is_forbidden(): void
    {
        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');

        $this->actingAs($panitia)
            ->get(route('admin.gallery.index'))
            ->assertForbidden();
    }

    public function test_branding_settings_save_with_optional_logo(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'foundation_name' => 'Yayasan Branding',
                'foundation_tagline' => 'Tagline branding.',
                'profile_video_url' => 'https://youtu.be/abc123XYZ',
                'site_logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('Yayasan Branding', Setting::get('foundation_name'));
        $this->assertSame('Tagline branding.', Setting::get('foundation_tagline'));
        $logo = Setting::get('site_logo');
        $this->assertNotNull($logo);
        Storage::disk('public')->assertExists($logo);

        // Removing the logo clears the setting and the file.
        $this->actingAs($admin)
            ->put(route('admin.settings.update'), ['remove_logo' => '1'])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertNull(Setting::get('site_logo'));
        Storage::disk('public')->assertMissing($logo);
    }
}
