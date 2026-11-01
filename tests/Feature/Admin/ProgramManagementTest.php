<?php

namespace Tests\Feature\Admin;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.programs.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.programs.index';
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    public function test_user_without_permission_cannot_access_program_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    public function test_admin_can_view_program_list(): void
    {
        $this->assertAdminCanViewList();
    }

    public function test_admin_can_create_program(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.programs.store'), [
                'name' => 'Tahfidz',
                'description' => 'Program unggulan tahfidz',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.programs.index'));
        $this->assertDatabaseHas('programs', ['name' => 'Tahfidz', 'description' => 'Program unggulan tahfidz']);
    }

    public function test_program_name_must_be_unique(): void
    {
        Program::factory()->create(['name' => 'Reguler']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.programs.store'), [
                'name' => 'Reguler',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_program(): void
    {
        $program = Program::factory()->create(['name' => 'Nama Lama']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.programs.update', $program), [
                'name' => 'Nama Baru',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.programs.index'));
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name' => 'Nama Baru']);
    }

    public function test_admin_can_delete_program(): void
    {
        $program = Program::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.programs.destroy', $program));

        $response->assertRedirect(route('admin.programs.index'));
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }
}
