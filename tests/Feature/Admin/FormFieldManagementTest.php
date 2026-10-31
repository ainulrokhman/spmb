<?php

namespace Tests\Feature\Admin;

use App\Models\FormField;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\Feature\Admin\Concerns\TestsResourceAccess;
use Tests\TestCase;

class FormFieldManagementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers, TestsResourceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    protected function indexRouteName(): string
    {
        return 'admin.form-fields.index';
    }

    protected function indexViewName(): string
    {
        return 'admin.form-fields.index';
    }

    /**
     * A valid form field payload for the given unit.
     */
    private function validFieldPayload(SchoolUnit $unit, array $overrides = []): array
    {
        return array_merge([
            'school_unit_id' => $unit->id,
            'key' => 'pekerjaan_ayah',
            'label' => 'Pekerjaan Ayah',
            'type' => 'text',
            'is_required' => '1',
            'section' => 'Data Orang Tua',
            'sort_order' => 1,
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * Test guest is redirected to login from the form field list.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->assertGuestIsRedirectedToLogin();
    }

    /**
     * Test a user without the form-field-list permission cannot access the list.
     */
    public function test_user_without_permission_cannot_access_form_field_list(): void
    {
        $this->assertUserWithoutPermissionCannotAccessList();
    }

    /**
     * Test an admin can view the form field list.
     */
    public function test_admin_can_view_form_field_list(): void
    {
        $this->assertAdminCanViewList();
    }

    /**
     * Test an admin can create a simple text field.
     */
    public function test_admin_can_create_text_field(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.form-fields.store'), $this->validFieldPayload($unit));

        $response->assertRedirect(route('admin.form-fields.index'));
        $this->assertDatabaseHas('form_fields', [
            'school_unit_id' => $unit->id,
            'key' => 'pekerjaan_ayah',
            'type' => 'text',
            'options' => null,
        ]);
    }

    /**
     * Test a choice field stores its options as a list, one per line.
     */
    public function test_choice_field_stores_parsed_options(): void
    {
        $unit = SchoolUnit::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.form-fields.store'), $this->validFieldPayload($unit, [
                'key' => 'golongan_darah',
                'label' => 'Golongan Darah',
                'type' => 'select',
                'options_text' => "A\nB\nAB\nO\n",
            ]))
            ->assertRedirect(route('admin.form-fields.index'));

        $field = FormField::where('key', 'golongan_darah')->firstOrFail();
        $this->assertSame(['A', 'B', 'AB', 'O'], $field->options);
    }

    /**
     * Test a choice field without options is rejected.
     */
    public function test_choice_field_requires_options(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.form-fields.store'), $this->validFieldPayload($unit, [
                'type' => 'radio',
                'options_text' => '',
            ]));

        $response->assertSessionHasErrors('options_text');
        $this->assertDatabaseCount('form_fields', 0);
    }

    /**
     * Test the field key must be a snake_case machine name.
     */
    public function test_field_key_must_be_snake_case(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.form-fields.store'), $this->validFieldPayload($unit, [
                'key' => 'Pekerjaan Ayah!',
            ]));

        $response->assertSessionHasErrors('key');
    }

    /**
     * Test a field key must be unique within its unit but may repeat across units.
     */
    public function test_field_key_is_unique_per_unit_only(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();
        FormField::factory()->create(['school_unit_id' => $unitA->id, 'key' => 'hobi']);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.form-fields.store'), $this->validFieldPayload($unitA, ['key' => 'hobi']));
        $response->assertSessionHasErrors('key');

        $response = $this->actingAs($this->admin())
            ->post(route('admin.form-fields.store'), $this->validFieldPayload($unitB, ['key' => 'hobi']));
        $response->assertRedirect(route('admin.form-fields.index'));
    }

    /**
     * Test an admin can update a form field.
     */
    public function test_admin_can_update_form_field(): void
    {
        $field = FormField::factory()->create(['type' => 'text', 'options' => null, 'label' => 'Hobi']);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.form-fields.update', $field), $this->validFieldPayload($field->schoolUnit, [
                'key' => $field->key,
                'label' => 'Hobi dan Minat',
            ]));

        $response->assertRedirect(route('admin.form-fields.index'));
        $this->assertDatabaseHas('form_fields', ['id' => $field->id, 'label' => 'Hobi dan Minat']);
    }

    /**
     * Test an admin can delete a form field.
     */
    public function test_admin_can_delete_form_field(): void
    {
        $field = FormField::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('admin.form-fields.destroy', $field));

        $response->assertRedirect(route('admin.form-fields.index'));
        $this->assertDatabaseMissing('form_fields', ['id' => $field->id]);
    }

    /**
     * Test the form preview renders the unit's active fields grouped by section.
     */
    public function test_admin_can_preview_unit_form(): void
    {
        $unit = SchoolUnit::factory()->create();
        FormField::factory()->create([
            'school_unit_id' => $unit->id,
            'label' => 'Nama Panggilan',
            'type' => 'text',
            'options' => null,
            'section' => 'Data Siswa',
            'is_active' => true,
        ]);
        FormField::factory()->create([
            'school_unit_id' => $unit->id,
            'label' => 'Bidang Tersembunyi',
            'type' => 'text',
            'options' => null,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.school-units.form-preview', $unit));

        $response->assertOk();
        $response->assertViewIs('admin.form-fields.preview');
        $response->assertSee('Nama Panggilan');
        $response->assertSee('Data Siswa');
        $response->assertDontSee('Bidang Tersembunyi');
    }

    /**
     * Test a user without the form-field-list permission cannot open the preview.
     */
    public function test_user_without_permission_cannot_preview_form(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->get(route('admin.school-units.form-preview', $unit));

        $response->assertForbidden();
    }
}
