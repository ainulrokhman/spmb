<?php

namespace Tests\Feature\Admin;

use App\Models\FeeComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class FeeComponentManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.fee-components.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.fee-components.index';
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    public function test_user_without_permission_cannot_access_fee_component_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    public function test_admin_can_view_fee_component_list(): void
    {
        $this->assertAdminCanViewList();
    }

    public function test_admin_can_create_fee_component_with_uppercased_code(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.fee-components.store'), [
                'code' => 'seragam',
                'name' => 'Seragam Sekolah',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.fee-components.index'));
        $this->assertDatabaseHas('fee_components', ['code' => 'SERAGAM', 'name' => 'Seragam Sekolah']);
    }

    public function test_fee_component_code_must_be_unique(): void
    {
        FeeComponent::factory()->create(['code' => 'SPI']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.fee-components.store'), [
                'code' => 'SPI',
                'name' => 'Duplikat',
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_admin_can_update_fee_component(): void
    {
        $component = FeeComponent::factory()->create(['name' => 'Nama Lama']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.fee-components.update', $component), [
                'code' => $component->code,
                'name' => 'Nama Baru',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.fee-components.index'));
        $this->assertDatabaseHas('fee_components', ['id' => $component->id, 'name' => 'Nama Baru']);
    }

    public function test_admin_can_delete_fee_component(): void
    {
        $component = FeeComponent::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.fee-components.destroy', $component));

        $response->assertRedirect(route('admin.fee-components.index'));
        $this->assertDatabaseMissing('fee_components', ['id' => $component->id]);
    }
}
