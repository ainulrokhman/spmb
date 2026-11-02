<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\FormField;
use App\Models\Major;
use App\Models\Program;
use App\Models\SchoolUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Regression tests for two display bugs reported on the master-data
 * tables: the unit label rendered the related Program model as raw
 * JSON (its __toString is toJson), and the wave table missed the
 * admission_tracks_count column because select() ran after
 * withCount() and replaced it.
 */
class UnitLabelDataTableTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private SchoolUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $program = Program::factory()->create(['name' => 'Boarding']);
        $this->unit = SchoolUnit::factory()->create([
            'name' => 'SMK Contoh Teknologi',
            'level' => 'SMK',
            'program_id' => $program->id,
        ]);
    }

    private function rows(string $routeName): array
    {
        $response = $this->actingAs($this->superAdmin())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route($routeName, ['draw' => 1, 'start' => 0, 'length' => 10]));

        $response->assertOk();

        return $response->json('data');
    }

    public function test_unit_labels_show_program_name_not_json(): void
    {
        AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id]);
        Major::factory()->create(['school_unit_id' => $this->unit->id]);
        AdmissionWave::factory()->create(['school_unit_id' => $this->unit->id]);
        FormField::factory()->create(['school_unit_id' => $this->unit->id]);

        foreach (['admin.admission-tracks.index', 'admin.majors.index', 'admin.admission-waves.index', 'admin.form-fields.index'] as $routeName) {
            $rows = $this->rows($routeName);
            $this->assertNotEmpty($rows, "{$routeName} returned no rows");
            $this->assertStringContainsString('Boarding', $rows[0]['unit_name'], "{$routeName} label misses the program name");
            $this->assertStringNotContainsString('{&quot;id&quot;', $rows[0]['unit_name'], "{$routeName} label leaks program JSON");
            $this->assertStringNotContainsString('{"id"', $rows[0]['unit_name'], "{$routeName} label leaks program JSON");
        }
    }

    public function test_wave_rows_include_admission_tracks_count(): void
    {
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $this->unit->id]);
        $wave = AdmissionWave::factory()->create(['school_unit_id' => $this->unit->id]);
        $wave->admissionTracks()->attach($track);

        $rows = $this->rows('admin.admission-waves.index');

        $this->assertArrayHasKey('admission_tracks_count', $rows[0]);
        $this->assertSame(1, $rows[0]['admission_tracks_count']);
    }

    public function test_unit_dropdowns_show_program_name_not_json(): void
    {
        foreach (['admin.admission-tracks.create', 'admin.majors.create', 'admin.admission-waves.create', 'admin.form-fields.create'] as $routeName) {
            $html = $this->actingAs($this->superAdmin())->get(route($routeName))->assertOk()->getContent();
            $this->assertStringContainsString('SMK Contoh Teknologi', $html, "{$routeName} misses the unit option");
            $this->assertStringNotContainsString('{"id"', $html, "{$routeName} leaks program JSON into the unit dropdown");
        }
    }
}
