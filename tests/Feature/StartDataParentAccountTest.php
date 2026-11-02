<?php

namespace Tests\Feature;

use App\Contracts\ApplicationServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\FormField;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Start-step data (child name and relation captured up front),
 * parent accounts created from the biodata checkboxes (never
 * for the account holder's own section, never when unticked),
 * and parent identities prefilled for later children.
 */
class StartDataParentAccountTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081234567890']);
        $this->guardian->assignRole('Wali');
    }

    /**
     * A free unit with an open wave, one track, one required
     * document and one required dynamic field.
     *
     * @return array{unit: SchoolUnit, wave: AdmissionWave, track: AdmissionTrack}
     */
    private function scenario(): array
    {
        $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => true]);
        $unit = SchoolUnit::factory()->create(['code' => '31', 'form_fee' => 0]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create(['school_unit_id' => $unit->id, 'academic_year_id' => $year->id]);
        $wave->admissionTracks()->attach($track);

        $docType = DocumentType::factory()->create(['code' => 'KK', 'name' => 'Kartu Keluarga']);
        DocumentRequirement::create([
            'school_unit_id' => $unit->id,
            'document_type_id' => $docType->id,
            'requirement' => DocumentRequirement::REQUIRED,
        ]);

        FormField::factory()->create([
            'school_unit_id' => $unit->id,
            'key' => 'asal_sekolah',
            'label' => 'Asal Sekolah',
            'type' => 'text',
            'is_required' => true,
            'is_active' => true,
        ]);

        return ['unit' => $unit, 'wave' => $wave, 'track' => $track];
    }

    private function startApplication(array $scenario, array $overrides = []): Application
    {
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), array_merge([
                'child_name' => 'Anak Pertama',
                'guardian_relation' => 'ayah',
                'school_unit_id' => $scenario['unit']->id,
                'admission_wave_id' => $scenario['wave']->id,
                'admission_track_id' => $scenario['track']->id,
            ], $overrides))
            ->assertRedirect();

        return Application::latest('id')->firstOrFail();
    }

    private function formPayload(array $overrides = []): array
    {
        return array_merge([
            'child_name' => 'Anak Pertama',
            'birth_place' => 'Jakarta',
            'birth_date' => '2015-05-01',
            'gender' => 'P',
            'father' => ['name' => 'Bapak', 'status' => 'Masih Hidup', 'phone' => '081211112222'],
            'mother' => ['name' => 'Ibu', 'status' => 'Masih Hidup', 'phone' => '081299988877'],
            'answers' => ['asal_sekolah' => 'SDN 01'],
        ], $overrides);
    }

    public function test_start_stores_child_name_and_relation(): void
    {
        $application = $this->startApplication($this->scenario());

        $this->assertSame('Anak Pertama', $application->child_name);
        $this->assertSame('ayah', $application->guardian_relation);
        $this->assertSame('filling_form', $application->status);

        $this->actingAs($this->guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Anak Pertama');
    }

    public function test_biodata_form_has_no_parent_name_fields(): void
    {
        $application = $this->startApplication($this->scenario());

        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.form', $application))
            ->assertOk()
            ->assertDontSee('name="parent_name"')
            ->assertDontSee('name="parent_phone"')
            ->assertSee('Hubungan Anda dengan Anak');

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->formPayload())
            ->assertRedirect(route('guardian.applications.documents', $application));

        $this->assertSame('uploading_documents', $application->fresh()->status);
    }

    public function test_account_checkbox_hidden_for_the_holders_own_section(): void
    {
        // The holder registered as the father: only the mother and
        // guardian sections offer an account checkbox.
        $application = $this->startApplication($this->scenario());

        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.form', $application))
            ->assertOk()
            ->assertDontSee('name="father[create_account]"', false)
            ->assertSee('name="mother[create_account]"', false)
            ->assertSee('name="guardian[create_account]"', false);
    }

    public function test_checked_section_creates_account_when_the_form_is_saved(): void
    {
        $application = $this->startApplication($this->scenario());

        $payload = $this->formPayload();
        $payload['mother']['create_account'] = '1';

        $response = $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $payload);

        $response->assertSessionHas('created_accounts');
        $created = session('created_accounts');
        $this->assertCount(1, $created);
        $this->assertSame('Ibu', $created[0]['label']);
        $this->assertSame('081299988877', $created[0]['phone']);
        $this->assertNotEmpty($created[0]['password']);

        $mother = User::where('phone', '081299988877')->firstOrFail();
        $this->assertTrue($mother->hasRole('Wali'));
        $this->assertTrue(Auth::attempt(['phone' => '081299988877', 'password' => $created[0]['password']]));
        Auth::logout();

        // The father's section was not ticked: no account for him.
        $this->assertNull(User::where('phone', '081211112222')->first());

        // Shared access: the mother sees and can open the application.
        $this->actingAs($mother)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Anak Pertama');

        $this->actingAs($mother)
            ->get(route('guardian.applications.show', $application))
            ->assertRedirect(route('guardian.applications.documents', $application));

        $stranger = User::factory()->create(['phone' => '081200001111']);
        $stranger->assignRole('Wali');
        $this->actingAs($stranger)
            ->get(route('guardian.applications.show', $application))
            ->assertNotFound();
    }

    public function test_holders_own_section_never_creates_an_account_even_if_flagged(): void
    {
        $application = $this->startApplication($this->scenario());

        $payload = $this->formPayload();
        $payload['father']['create_account'] = '1'; // holder IS the father

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $payload)
            ->assertRedirect();

        $this->assertNull(User::where('phone', '081211112222')->first());
        $this->assertSame(0, $application->fresh()->linkedUsers()->count());
    }

    public function test_unticked_sections_create_no_accounts(): void
    {
        $application = $this->startApplication($this->scenario());

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->formPayload())
            ->assertRedirect();

        $this->assertNull(User::where('phone', '081299988877')->first());
        $this->assertNull(User::where('phone', '081211112222')->first());
        $this->assertSame(0, $application->fresh()->linkedUsers()->count());
    }

    public function test_existing_account_is_linked_not_duplicated(): void
    {
        $existing = User::factory()->create(['phone' => '081299988877', 'name' => 'Ibu Terdaftar']);
        $existing->assignRole('Wali');

        $service = app(ApplicationServiceInterface::class);
        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'guardian_relation' => 'ayah',
            'status' => 'filling_form',
        ]);

        $payload = $this->formPayload();
        $payload['mother']['create_account'] = '1';

        $results = $service->createParentAccounts($application, $payload);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]['password']);
        $this->assertSame(1, User::where('phone', '081299988877')->count());
        $this->assertTrue($application->linkedUsers()->whereKey($existing->id)->exists());
    }

    public function test_second_child_prefills_parent_identities_from_the_previous_application(): void
    {
        $scenario = $this->scenario();
        $first = $this->startApplication($scenario, ['child_name' => 'Anak Sulung']);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $first), $this->formPayload([
                'child_name' => 'Anak Sulung',
                'father' => ['name' => 'Bapak Sulung', 'status' => 'Masih Hidup', 'phone' => '081211112222', 'job' => 'Petani'],
                'mother' => ['name' => 'Ibu Sulung', 'status' => 'Masih Hidup', 'phone' => '081299988877'],
            ]))
            ->assertRedirect();

        $second = $this->startApplication($scenario, ['child_name' => 'Anak Bungsu']);

        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.form', $second))
            ->assertOk()
            ->assertSee('value="Bapak Sulung"', false)
            ->assertSee('value="Ibu Sulung"', false)
            ->assertSee('value="081299988877"', false);
    }
}
