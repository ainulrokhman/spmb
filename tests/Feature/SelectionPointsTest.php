<?php

namespace Tests\Feature;

use App\Contracts\SelectionServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\ChildStatus;
use App\Models\SchoolUnit;
use App\Models\SelectionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class SelectionPointsTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private AcademicYear $year;

    private int $codeSeq = 69;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081200007777']);
        $this->guardian->assignRole('Wali');
        $this->year = AcademicYear::factory()->create(['name' => '2027/2028', 'is_active' => true]);
    }

    private function unit(bool $enabled = true): SchoolUnit
    {
        return SchoolUnit::factory()->create([
            'code' => (string) (++$this->codeSeq),
            'selection_enabled' => $enabled,
        ]);
    }

    private function rule(SchoolUnit $unit, string $kind, string $name, array $config, int $order = 0): SelectionRule
    {
        return SelectionRule::create([
            'school_unit_id' => $unit->id,
            'kind' => $kind,
            'name' => $name,
            'config' => $config,
            'sort_order' => $order,
            'is_active' => true,
        ]);
    }

    private function application(SchoolUnit $unit, array $attributes = []): Application
    {
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $this->year->id,
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);

        return Application::factory()->create(array_merge([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $this->year->id,
            'status' => 'verified',
            'birth_date' => '2020-08-15',
            'answers' => [],
        ], $attributes));
    }

    private function selection(): SelectionServiceInterface
    {
        return app(SelectionServiceInterface::class);
    }

    public function test_age_brackets_use_july_first_of_the_academic_year(): void
    {
        $unit = $this->unit();
        $this->rule($unit, 'age', 'Umur', ['brackets' => [
            ['min' => 5, 'max' => 6, 'points' => 10],
            ['min' => 7, 'max' => null, 'points' => 5],
        ]]);

        // Born Aug 2020 → still 6 on 1 July 2027.
        $six = $this->application($unit, ['birth_date' => '2020-08-15']);
        $snapshot = $this->selection()->calculate($six);
        $this->assertSame(10, $snapshot['total']);
        $this->assertStringContainsString('Umur 6 tahun', $snapshot['auto'][0]['detail']);

        // Born Jun 2020 → already 7 on 1 July 2027.
        $seven = $this->application($unit, ['birth_date' => '2020-06-30']);
        $this->assertSame(5, $this->selection()->calculate($seven)['total']);
    }

    public function test_choice_numeric_origin_and_child_status_rules(): void
    {
        $origin = $this->unit();
        $unit = $this->unit();
        $yatim = ChildStatus::create(['name' => 'Yatim', 'is_active' => true]);
        ChildStatus::create(['name' => 'Reguler', 'is_active' => true]);

        $this->rule($unit, 'choice', 'Pekerjaan', ['field_key' => 'pekerjaan', 'map' => ['Guru' => 7, 'Petani' => 3]], 1);
        $this->rule($unit, 'numeric', 'Penghasilan', ['field_key' => 'penghasilan', 'ranges' => [
            ['min' => 0, 'max' => 100, 'points' => 4],
            ['min' => 101, 'max' => null, 'points' => 1],
        ]], 2);
        $this->rule($unit, 'origin_unit', 'Asal Sekolah', ['field_key' => 'asal', 'points' => 12], 3);
        $this->rule($unit, 'child_status', 'Status Anak', ['map' => [$yatim->id => 15]], 4);

        $application = $this->application($unit, [
            'child_status_id' => $yatim->id,
            'answers' => ['pekerjaan' => 'Guru', 'penghasilan' => 50, 'asal' => $origin->id],
        ]);

        $snapshot = $this->selection()->calculate($application);
        $this->assertSame(7 + 4 + 12 + 15, $snapshot['total']);
        $this->assertSame(38, $application->fresh()->selection_total);
        $this->assertStringContainsString('Lulusan', $snapshot['auto'][2]['detail']);

        $external = $this->application($unit, ['answers' => ['asal' => 'external']]);
        $originRow = collect($this->selection()->calculate($external)['auto'])->firstWhere('kind', 'origin_unit');
        $this->assertSame(0, $originRow['points']);

        // The structured storage shape ({unit_id, school_name})
        // written by the guardian form resolves the same way.
        $structured = $this->application($unit, ['answers' => ['asal' => ['unit_id' => $origin->id, 'school_name' => null]]]);
        $originRow = collect($this->selection()->calculate($structured)['auto'])->firstWhere('kind', 'origin_unit');
        $this->assertSame(12, $originRow['points']);

        $structuredExternal = $this->application($unit, ['answers' => ['asal' => ['unit_id' => null, 'school_name' => 'SD Luar Biasa']]]);
        $originRow = collect($this->selection()->calculate($structuredExternal)['auto'])->firstWhere('kind', 'origin_unit');
        $this->assertSame(0, $originRow['points']);
        $this->assertStringContainsString('SD Luar Biasa', $originRow['detail']);
    }

    public function test_sibling_points_with_cap_and_cross_check_hint(): void
    {
        $origin = $this->unit();
        $unit = $this->unit();
        $this->rule($unit, 'siblings', 'Saudara', [
            'field_key' => 'saudara', 'points_internal' => 10, 'points_external' => 2, 'cap' => 15,
        ]);

        // An actually re-registered older sibling at the origin unit.
        $this->application($origin, ['child_name' => 'Kakak Nyata', 'status' => 're_registered']);

        $application = $this->application($unit, ['answers' => ['saudara' => [
            ['name' => 'Kakak Nyata', 'unit_id' => $origin->id, 'school_name' => null],
            ['name' => 'Kakak Klaim', 'unit_id' => $origin->id, 'school_name' => null],
            ['name' => 'Adik Luar', 'unit_id' => null, 'school_name' => 'SD Jauh'],
        ]]]);

        $snapshot = $this->selection()->calculate($application);
        // 10 + 10 + 2 = 22, capped at 15.
        $this->assertSame(15, $snapshot['total']);
        $this->assertStringContainsString('cocok dengan siswa daftar ulang', $snapshot['auto'][0]['detail']);
        $this->assertStringContainsString('belum ditemukan', $snapshot['auto'][0]['detail']);
    }

    public function test_disabled_unit_calculates_nothing(): void
    {
        $unit = $this->unit(enabled: false);
        $this->rule($unit, 'age', 'Umur', ['brackets' => [['min' => 0, 'max' => null, 'points' => 10]]]);

        $this->assertNull($this->selection()->calculate($this->application($unit)));
    }

    public function test_manual_scores_and_adjustment_survive_recalculation_and_lock_after_decision(): void
    {
        $unit = $this->unit();
        $manual = $this->rule($unit, 'manual', 'Wawancara', ['max_points' => 20]);
        $this->rule($unit, 'age', 'Umur', ['brackets' => [['min' => 0, 'max' => null, 'points' => 10]]], 1);
        $application = $this->application($unit);
        $admin = $this->admin();

        $snapshot = $this->selection()->setManualScore($application, $manual, 15, $admin);
        $this->assertSame(25, $snapshot['total']);

        $this->expectException(InvalidArgumentException::class);
        $this->selection()->setManualScore($application, $manual, 21, $admin);
    }

    public function test_adjustment_needs_reason_and_locks_after_decision(): void
    {
        $unit = $this->unit();
        $this->rule($unit, 'age', 'Umur', ['brackets' => [['min' => 0, 'max' => null, 'points' => 10]]]);
        $application = $this->application($unit);
        $admin = $this->admin();

        try {
            $this->selection()->setAdjustment($application, 5, null, $admin);
            $this->fail('Adjustment without a reason must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Alasan wajib', $e->getMessage());
        }

        $snapshot = $this->selection()->setAdjustment($application, -3, 'Kebijakan yayasan', $admin);
        $this->assertSame(7, $snapshot['total']);
        $this->assertSame('Kebijakan yayasan', $snapshot['adjustment']['reason']);

        // Recalculation preserves the adjustment.
        $this->assertSame(7, $this->selection()->calculate($application)['total']);

        $application->update(['status' => 'passed']);
        $this->expectException(InvalidArgumentException::class);
        $this->selection()->setAdjustment($application, 1, 'Terlambat', $admin);
    }

    public function test_apply_by_points_decides_verified_applications_by_the_wave_threshold(): void
    {
        $unit = $this->unit();
        $this->rule($unit, 'age', 'Umur', ['brackets' => [
            ['min' => 5, 'max' => 6, 'points' => 10],
            ['min' => 7, 'max' => null, 'points' => 5],
        ]]);

        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $this->year->id,
            'passing_score' => 8,
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $make = fn (string $birth) => Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $this->year->id,
            'status' => 'verified',
            'birth_date' => $birth,
            'answers' => [],
        ]);

        $high = $make('2020-08-15'); // 10 points
        $low = $make('2020-06-30');  // 5 points

        $preview = $this->selection()->previewByPoints($wave);
        $this->assertSame([10, 5], $preview->pluck('total')->all());
        $this->assertSame(['passed', 'failed'], $preview->pluck('decision')->all());

        $counts = $this->selection()->applyByPoints($wave);
        $this->assertSame(['passed' => 1, 'failed' => 1], $counts);
        $this->assertSame('passed', $high->fresh()->status);
        $this->assertSame('failed', $low->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Admin UI
    // ---------------------------------------------------------------

    public function test_admin_manages_rules_and_toggle_and_other_unit_panitia_is_blocked(): void
    {
        $unit = $this->unit(enabled: false);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('admin.school-units.selection.toggle', $unit))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertTrue($unit->fresh()->selection_enabled);

        $this->actingAs($admin)
            ->post(route('admin.school-units.selection.store', $unit), [
                'kind' => 'age',
                'name' => 'Umur Ideal',
                'brackets_text' => "5|6|10\n7||5",
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.school-units.selection.index', $unit));

        $rule = SelectionRule::where('name', 'Umur Ideal')->firstOrFail();
        // JSON storage returns whole numbers as ints; the values
        // themselves are what matters.
        $this->assertEquals([
            ['min' => 5, 'max' => 6, 'points' => 10],
            ['min' => 7, 'max' => null, 'points' => 5],
        ], $rule->config['brackets']);

        $this->actingAs($admin)
            ->post(route('admin.school-units.selection.store', $unit), [
                'kind' => 'age',
                'name' => 'Umur Rusak',
                'brackets_text' => 'lima|enam|sepuluh',
                'is_active' => '1',
            ])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('selection_rules', ['name' => 'Umur Rusak']);

        $this->actingAs($admin)
            ->get(route('admin.school-units.selection.index', $unit))
            ->assertOk()
            ->assertSee('Umur Ideal')
            ->assertSee('Seleksi Aktif');

        $otherUnit = $this->unit();
        $panitia = User::factory()->create();
        $panitia->assignRole('Panitia');
        $panitia->schoolUnits()->sync([$otherUnit->id]);
        $this->actingAs($panitia)
            ->get(route('admin.school-units.selection.index', $unit))
            ->assertForbidden();
    }

    public function test_verification_triggers_scoring_and_card_actions_work(): void
    {
        $unit = $this->unit();
        $manual = $this->rule($unit, 'manual', 'Wawancara', ['max_points' => 20]);
        $this->rule($unit, 'age', 'Umur', ['brackets' => [['min' => 0, 'max' => null, 'points' => 10]]], 1);
        $application = $this->application($unit, ['status' => 'submitted']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.applications.decide', $application), ['decision' => 'verified'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(10, $application->fresh()->selection_total);

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Poin Seleksi')
            ->assertSee('Wawancara');

        $this->actingAs($admin)
            ->post(route('admin.applications.selection.manual', $application), ['rule_id' => $manual->id, 'points' => 12])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(22, $application->fresh()->selection_total);

        $this->actingAs($admin)
            ->post(route('admin.applications.selection.adjustment', $application), ['value' => -2, 'reason' => 'Berkas kurang rapi'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(20, $application->fresh()->selection_total);

        $this->actingAs($admin)
            ->post(route('admin.applications.selection.recalculate', $application))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(20, $application->fresh()->selection_total);
    }

    public function test_by_points_preview_and_apply_over_http(): void
    {
        $unit = $this->unit();
        $this->rule($unit, 'age', 'Umur', ['brackets' => [['min' => 0, 'max' => null, 'points' => 10]]]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $this->year->id,
            'passing_score' => 8,
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $this->year->id,
            'status' => 'verified',
            'birth_date' => '2020-08-15',
            'answers' => [],
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.selection.by-points', ['admission_wave_id' => $wave->id]))
            ->assertOk()
            ->assertSee('Tetapkan dari Poin')
            ->assertSee($application->child_name);

        $this->actingAs($admin)
            ->post(route('admin.selection.by-points.apply'), ['admission_wave_id' => $wave->id])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('passed', $application->fresh()->status);
    }
}
