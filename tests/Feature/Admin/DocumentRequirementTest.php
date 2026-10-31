<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class DocumentRequirementTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    /**
     * Test an admin can open the document requirement matrix of a unit.
     */
    public function test_admin_can_view_document_requirement_matrix(): void
    {
        $unit = SchoolUnit::factory()->create();
        DocumentType::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.school-units.document-requirements.edit', $unit));

        $response->assertOk();
        $response->assertViewIs('admin.school-units.document-requirements');
    }

    /**
     * Test a user without the school-unit-edit permission cannot open the matrix.
     */
    public function test_user_without_permission_cannot_view_matrix(): void
    {
        $unit = SchoolUnit::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->get(route('admin.school-units.document-requirements.edit', $unit));

        $response->assertForbidden();
    }

    /**
     * Test saving the matrix stores required and optional types and drops unused ones.
     */
    public function test_admin_can_save_document_requirements(): void
    {
        $unit = SchoolUnit::factory()->create();
        $required = DocumentType::factory()->create();
        $optional = DocumentType::factory()->create();
        $dropped = DocumentType::factory()->create();
        DocumentRequirement::factory()->create([
            'school_unit_id' => $unit->id,
            'document_type_id' => $dropped->id,
            'requirement' => DocumentRequirement::REQUIRED,
        ]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.school-units.document-requirements.update', $unit), [
                'requirements' => [
                    $required->id => 'required',
                    $optional->id => 'optional',
                    $dropped->id => 'unused',
                ],
            ]);

        $response->assertRedirect(route('admin.school-units.index'));
        $this->assertDatabaseHas('document_requirements', [
            'school_unit_id' => $unit->id,
            'document_type_id' => $required->id,
            'requirement' => 'required',
        ]);
        $this->assertDatabaseHas('document_requirements', [
            'school_unit_id' => $unit->id,
            'document_type_id' => $optional->id,
            'requirement' => 'optional',
        ]);
        $this->assertDatabaseMissing('document_requirements', [
            'school_unit_id' => $unit->id,
            'document_type_id' => $dropped->id,
        ]);
    }

    /**
     * Test an invalid requirement level is rejected.
     */
    public function test_invalid_requirement_level_is_rejected(): void
    {
        $unit = SchoolUnit::factory()->create();
        $type = DocumentType::factory()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('admin.school-units.document-requirements.update', $unit), [
                'requirements' => [$type->id => 'wajib-banget'],
            ]);

        $response->assertSessionHasErrors('requirements.'.$type->id);
    }

    /**
     * Test requirements of one unit do not affect another unit.
     */
    public function test_requirements_are_scoped_per_unit(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();
        $type = DocumentType::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.school-units.document-requirements.update', $unitA), [
                'requirements' => [$type->id => 'required'],
            ])
            ->assertRedirect(route('admin.school-units.index'));

        $this->assertDatabaseHas('document_requirements', [
            'school_unit_id' => $unitA->id,
            'document_type_id' => $type->id,
        ]);
        $this->assertDatabaseMissing('document_requirements', [
            'school_unit_id' => $unitB->id,
            'document_type_id' => $type->id,
        ]);
    }
}
