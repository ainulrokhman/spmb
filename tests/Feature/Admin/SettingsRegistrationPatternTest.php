<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Support\RegistrationNumberFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class SettingsRegistrationPatternTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    public function test_settings_page_shows_the_default_pattern_and_preview(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('{unit}{tahun2}{urut:3}')
            ->assertSee('3126001');
    }

    public function test_admin_can_save_a_custom_pattern(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('admin.settings.update'), [
                'registration_number_pattern' => 'PPDB-{tahun}-{urut:4}',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('success');

        $this->assertSame('PPDB-{tahun}-{urut:4}', Setting::get('registration_number_pattern'));
        $this->assertSame('PPDB-{tahun}-{urut:4}', RegistrationNumberFormatter::pattern());
    }

    public function test_admin_cannot_save_a_pattern_without_sequence(): void
    {
        $this->actingAs($this->superAdmin())
            ->put(route('admin.settings.update'), [
                'registration_number_pattern' => '{unit}-{tahun}',
            ])
            ->assertSessionHasErrors('registration_number_pattern');

        $this->assertNull(Setting::get('registration_number_pattern'));
    }
}
