<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\FormField;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class FormFieldTypesTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private int $codeSeq = 60;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081200008888']);
        $this->guardian->assignRole('Wali');
    }

    private function unit(string $name): SchoolUnit
    {
        return SchoolUnit::factory()->create([
            'code' => (string) (++$this->codeSeq),
            'name' => $name,
            'form_fee' => 0,
        ]);
    }

    /**
     * A registration unit carrying one school_unit field
     * (asal_sekolah) and one siblings field (saudara), plus a
     * second foundation unit the answers can point at.
     *
     * @return array{unit: SchoolUnit, other: SchoolUnit, wave: AdmissionWave, track: AdmissionTrack}
     */
    private function scenario(bool $siblingsRequired = false): array
    {
        $unit = $this->unit('SMP Tujuan');
        $other = $this->unit('SD Asal');

        $year = AcademicYear::factory()->create(['is_active' => true]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave->admissionTracks()->attach($track->id);

        FormField::factory()->create([
            'school_unit_id' => $unit->id,
            'key' => 'asal_sekolah',
            'label' => 'Asal Sekolah',
            'type' => 'school_unit',
            'is_required' => true,
            'is_active' => true,
        ]);
        FormField::factory()->create([
            'school_unit_id' => $unit->id,
            'key' => 'saudara',
            'label' => 'Saudara Kandung',
            'type' => 'siblings',
            'is_required' => $siblingsRequired,
            'is_active' => true,
        ]);

        return ['unit' => $unit, 'other' => $other, 'wave' => $wave, 'track' => $track];
    }

    private function startedApplication(array $scenario): Application
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), [
                'child_name' => 'Anak Uji',
                'guardian_relation' => 'ayah',
                'school_unit_id' => $scenario['unit']->id,
                'admission_wave_id' => $scenario['wave']->id,
                'admission_track_id' => $scenario['track']->id,
            ])
            ->assertRedirect();

        return Application::latest('id')->firstOrFail();
    }

    private function biodataPayload(array $answers): array
    {
        return [
            'child_name' => 'Anak Uji',
            'birth_place' => 'Jakarta',
            'birth_date' => '2016-05-01',
            'gender' => 'L',
            'father' => ['name' => 'Bapak', 'status' => 'Masih Hidup'],
            'mother' => ['name' => 'Ibu', 'status' => 'Masih Hidup'],
            'answers' => $answers,
        ];
    }

    public function test_admin_creates_master_data_fields_without_options(): void
    {
        $scenario = $this->scenario();
        $admin = $this->admin();

        foreach (['school_unit', 'siblings'] as $type) {
            $this->actingAs($admin)
                ->post(route('admin.form-fields.store'), [
                    'school_unit_id' => $scenario['unit']->id,
                    'key' => 'bidang_'.$type,
                    'label' => 'Bidang '.$type,
                    'type' => $type,
                    'is_required' => '0',
                    'section' => 'Tambahan',
                    'sort_order' => 5,
                    'is_active' => '1',
                ])
                ->assertRedirect(route('admin.form-fields.index'));
            $this->assertDatabaseHas('form_fields', ['key' => 'bidang_'.$type, 'type' => $type]);
        }

        // Preview renders the master-data options for both types.
        $this->actingAs($admin)
            ->get(route('admin.school-units.form-preview', $scenario['unit']))
            ->assertOk()
            ->assertSee('SD Asal')
            ->assertSee('Sekolah lain di luar yayasan')
            ->assertSee('Nama saudara');
    }

    public function test_guardian_answers_are_validated_and_stored_in_storage_shape(): void
    {
        $scenario = $this->scenario();
        $application = $this->startedApplication($scenario);

        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.form', $application))
            ->assertOk()
            ->assertSee('SD Asal')
            ->assertSee('Sekolah lain di luar yayasan')
            ->assertSee('Nama sekolah luar')
            ->assertSee('Tambah Saudara');

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => (string) $scenario['other']->id,
                'saudara' => [
                    ['name' => 'Kakak Satu', 'school' => (string) $scenario['other']->id, 'school_name' => ''],
                    ['name' => 'Kakak Dua', 'school' => 'external', 'school_name' => 'SD Luar Biasa'],
                    ['name' => '', 'school' => '', 'school_name' => ''],
                ],
            ]))
            ->assertRedirect(route('guardian.applications.documents', $application));

        $answers = $application->fresh()->answers;
        $this->assertSame(['unit_id' => $scenario['other']->id, 'school_name' => null], $answers['asal_sekolah']);
        $this->assertSame([
            ['name' => 'Kakak Satu', 'unit_id' => $scenario['other']->id, 'school_name' => null],
            ['name' => 'Kakak Dua', 'unit_id' => null, 'school_name' => 'SD Luar Biasa'],
        ], $answers['saudara']);
    }

    public function test_invalid_school_answers_are_rejected(): void
    {
        $scenario = $this->scenario();
        $application = $this->startedApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => '99999',
            ]))
            ->assertSessionHasErrors('answers.asal_sekolah');
        $this->assertSame('filling_form', $application->fresh()->status);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => 'external',
                'saudara' => [
                    ['name' => 'Kakak', 'school' => 'external', 'school_name' => ''],
                ],
            ]))
            ->assertSessionHasErrors('answers.saudara.0.school_name');
    }

    public function test_external_origin_school_requires_and_stores_its_name(): void
    {
        $scenario = $this->scenario();
        $application = $this->startedApplication($scenario);

        // External chosen without the school name -> rejected.
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => 'external',
            ]))
            ->assertSessionHasErrors('school_unit_names.asal_sekolah');
        $this->assertSame('filling_form', $application->fresh()->status);

        // With the name -> stored in the structured shape and
        // shown to the committee on the application detail.
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => 'external',
            ]) + ['school_unit_names' => ['asal_sekolah' => 'SD Luar Biasa']])
            ->assertRedirect(route('guardian.applications.documents', $application));

        $this->assertSame(
            ['unit_id' => null, 'school_name' => 'SD Luar Biasa'],
            $application->fresh()->answers['asal_sekolah']
        );

        $this->actingAs($this->admin())
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('SD Luar Biasa');
    }

    public function test_required_siblings_field_needs_at_least_one_row(): void
    {
        $scenario = $this->scenario(siblingsRequired: true);
        $application = $this->startedApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => 'external',
                'saudara' => [
                    ['name' => '', 'school' => '', 'school_name' => ''],
                ],
            ]))
            ->assertSessionHasErrors('answers.saudara');
    }

    public function test_committee_sees_resolved_school_names(): void
    {
        $scenario = $this->scenario();
        $application = $this->startedApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->biodataPayload([
                'asal_sekolah' => (string) $scenario['other']->id,
                'saudara' => [
                    ['name' => 'Kakak Satu', 'school' => (string) $scenario['other']->id, 'school_name' => ''],
                ],
            ]))
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('SD Asal')
            ->assertSee('Kakak Satu (SD Asal)');
    }

    public function test_answer_display_resolves_external_and_missing_units(): void
    {
        $field = FormField::factory()->make(['type' => 'school_unit']);

        $this->assertSame('Sekolah lain di luar yayasan', $field->answerDisplay('external'));
        $this->assertSame('Sekolah lain di luar yayasan: SD Luar Biasa', $field->answerDisplay(['unit_id' => null, 'school_name' => 'SD Luar Biasa']));
        $unit = $this->unit('SD Uji');
        $this->assertSame('SD Uji', $field->answerDisplay(['unit_id' => $unit->id, 'school_name' => null]));
        $this->assertSame('Unit tidak ditemukan', $field->answerDisplay(424242));
        $this->assertSame('—', $field->answerDisplay(null));
    }
}
