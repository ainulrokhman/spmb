<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\FormField;
use App\Models\Major;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 4 of the filter-persistence work: the unit-filtered
 * master-data lists (Jalur, Jurusan, Gelombang, Bidang
 * Formulir) restore their unit filter after create / edit /
 * delete, exactly like Nominal Biaya did in Fase 1.
 */
class MasterDataFilterPersistenceTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $admin;

    private SchoolUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->admin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $this->unit = SchoolUnit::factory()->create();
    }

    private function filtered(string $routeName): string
    {
        return route($routeName, ['school_unit_id' => $this->unit->id]);
    }

    public function test_track_store_update_delete_return_to_the_filtered_list(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.admission-tracks.store'), [
                'school_unit_id' => $this->unit->id,
                'name' => 'Jalur Uji',
                'return_school_unit_id' => $this->unit->id,
            ])
            ->assertRedirect($this->filtered('admin.admission-tracks.index'));

        $track = AdmissionTrack::where('name', 'Jalur Uji')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.admission-tracks.update', $track), [
                'school_unit_id' => $this->unit->id,
                'name' => 'Jalur Uji Ubah',
                'return_school_unit_id' => $this->unit->id,
            ])
            ->assertRedirect($this->filtered('admin.admission-tracks.index'));

        $this->actingAs($this->admin)
            ->delete(route('admin.admission-tracks.destroy', $track), [
                'return_school_unit_id' => $this->unit->id,
            ])
            ->assertRedirect($this->filtered('admin.admission-tracks.index'));
    }

    public function test_major_and_wave_and_form_field_store_return_to_the_filtered_list(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.majors.store'), [
                'school_unit_id' => $this->unit->id,
                'name' => 'Jurusan Uji',
                'return_school_unit_id' => $this->unit->id,
            ])
            ->assertRedirect($this->filtered('admin.majors.index'));

        $year = AcademicYear::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.admission-waves.store'), [
                'school_unit_id' => $this->unit->id,
                'academic_year_id' => $year->id,
                'name' => 'Gelombang Uji',
                'opens_at' => '2026-01-01',
                'closes_at' => '2026-02-01',
                'return_school_unit_id' => $this->unit->id,
            ])
            ->assertRedirect($this->filtered('admin.admission-waves.index'));

        $this->actingAs($this->admin)
            ->post(route('admin.form-fields.store'), [
                'school_unit_id' => $this->unit->id,
                'key' => 'bidang_uji',
                'label' => 'Bidang Uji',
                'type' => 'text',
                'return_school_unit_id' => $this->unit->id,
            ])
            ->assertRedirect($this->filtered('admin.form-fields.index'));
    }

    public function test_index_selects_the_filtered_unit_and_create_form_preselects_it(): void
    {
        $pages = [
            ['admin.admission-tracks.index', 'admin.admission-tracks.create'],
            ['admin.majors.index', 'admin.majors.create'],
            ['admin.admission-waves.index', 'admin.admission-waves.create'],
            ['admin.form-fields.index', 'admin.form-fields.create'],
        ];

        foreach ($pages as [$index, $create]) {
            $this->actingAs($this->admin)
                ->get(route($index, ['school_unit_id' => $this->unit->id]))
                ->assertOk()
                ->assertSee('value="'.$this->unit->id.'" selected', false)
                ->assertSee('id="create-btn"', false);

            $this->actingAs($this->admin)
                ->get(route($create, ['school_unit_id' => $this->unit->id]))
                ->assertOk()
                ->assertSee('name="return_school_unit_id" value="'.$this->unit->id.'"', false)
                ->assertSee('value="'.$this->unit->id.'" selected', false);
        }
    }

    public function test_row_actions_carry_the_active_unit_filter(): void
    {
        AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id]);
        FormField::factory()->create(['school_unit_id' => $this->unit->id]);

        foreach (['admin.admission-tracks.index', 'admin.form-fields.index'] as $index) {
            $response = $this->actingAs($this->admin)
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route($index, ['school_unit_id' => $this->unit->id]));

            $response->assertOk();
            $row = collect($response->json('data'))->first();
            $this->assertNotNull($row, $index);
            $this->assertStringContainsString('?school_unit_id='.$this->unit->id, $row['actions']);
            $this->assertStringContainsString('name="return_school_unit_id" value="'.$this->unit->id.'"', $row['actions']);
        }
    }

    public function test_without_a_filter_the_redirects_stay_plain(): void
    {
        $major = Major::factory()->create(['school_unit_id' => $this->unit->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.majors.destroy', $major))
            ->assertRedirect(route('admin.majors.index'));
    }
}
