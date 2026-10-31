<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class DocumentTypeManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.document-types.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.document-types.index';
    }

    /**
     * A valid document type payload.
     */
    private function validTypePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kartu Keluarga',
            'code' => 'kartu-keluarga',
            'allowed_extensions' => 'jpg,jpeg,png,pdf',
            'max_size_kb' => 2048,
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * Test guest is redirected to login from the document type list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the document-type-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_document_type_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the document type list.
     */
    public function test_admin_can_view_document_type_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create a document type and extensions are normalized.
     */
    public function test_admin_can_create_document_type(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.document-types.store'), $this->validTypePayload([
                'allowed_extensions' => 'JPG, PDF ,jpg',
            ]));

        $response->assertRedirect(route('admin.document-types.index'));
        $this->assertDatabaseHas('document_types', [
            'code' => 'kartu-keluarga',
            'allowed_extensions' => 'jpg,pdf',
        ]);
    }

    /**
     * Test the document type name and code must be unique.
     */
    public function test_document_type_name_and_code_must_be_unique(): void
    {
        DocumentType::factory()->create(['name' => 'Kartu Keluarga', 'code' => 'kartu-keluarga']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.document-types.store'), $this->validTypePayload(['code' => 'kk-lain']));
        $response->assertSessionHasErrors('name');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.document-types.store'), $this->validTypePayload(['name' => 'KK Lain']));
        $response->assertSessionHasErrors('code');
    }

    /**
     * Test an admin can update a document type.
     */
    public function test_admin_can_update_document_type(): void
    {
        $type = DocumentType::factory()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('admin.document-types.update', $type), $this->validTypePayload([
                'name' => 'Kartu Keluarga Baru',
                'code' => $type->code,
            ]));

        $response->assertRedirect(route('admin.document-types.index'));
        $this->assertDatabaseHas('document_types', ['id' => $type->id, 'name' => 'Kartu Keluarga Baru']);
    }

    /**
     * Test an admin can delete a document type.
     */
    public function test_admin_can_delete_document_type(): void
    {
        $type = DocumentType::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.document-types.destroy', $type));

        $response->assertRedirect(route('admin.document-types.index'));
        $this->assertDatabaseMissing('document_types', ['id' => $type->id]);
    }
}
