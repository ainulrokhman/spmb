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
 * Start-step data (child name, relation, parent-account opt-in)
 * and parent accounts created from the register menu's checkbox:
 * the account belongs to the parent of the relation type
 * declared at the start and shares access to the application.
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
                'guardian_relation' => 'ibu',
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

    public function test_start_stores_child_name_relation_and_parent_account_flag(): void
    {
        $application = $this->startApplication($this->scenario(), ['create_parent_account' => '1']);

        $this->assertSame('Anak Pertama', $application->child_name);
        $this->assertSame('ibu', $application->guardian_relation);
        $this->assertTrue((bool) $application->create_parent_account);
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

    public function test_parent_account_created_for_the_declared_relation(): void
    {
        $application = $this->startApplication($this->scenario(), ['create_parent_account' => '1']);

        $response = $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->formPayload());

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

        // The father's phone got no account: only the declared
        // relation (ibu) is created.
        $this->assertNull(User::where('phone', '081211112222')->first());

        // Shared access: the mother sees and can open the application.
        $this->actingAs($mother)
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Anak Pertama');

        $this->actingAs($mother)
            ->get(route('guardian.applications.show', $application))
            ->assertRedirect(route('guardian.applications.documents', $application));

        // An unrelated guardian cannot.
        $stranger = User::factory()->create(['phone' => '081200001111']);
        $stranger->assignRole('Wali');
        $this->actingAs($stranger)
            ->get(route('guardian.applications.show', $application))
            ->assertNotFound();
    }

    public function test_no_account_created_without_the_checkbox(): void
    {
        $application = $this->startApplication($this->scenario());

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->formPayload())
            ->assertRedirect();

        $this->assertNull(User::where('phone', '081299988877')->first());
        $this->assertSame(0, $application->fresh()->linkedUsers()->count());
    }

    public function test_existing_account_is_linked_not_duplicated(): void
    {
        $existing = User::factory()->create(['phone' => '081299988877', 'name' => 'Ibu Terdaftar']);
        $existing->assignRole('Wali');

        $service = app(ApplicationServiceInterface::class);
        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'guardian_relation' => 'ibu',
            'create_parent_account' => true,
            'status' => 'filling_form',
        ]);

        $results = $service->createParentAccounts($application, $this->formPayload());

        $this->assertCount(1, $results);
        $this->assertNull($results[0]['password']);
        $this->assertSame(1, User::where('phone', '081299988877')->count());
        $this->assertTrue($application->linkedUsers()->whereKey($existing->id)->exists());
    }

    public function test_registrants_own_number_is_left_alone(): void
    {
        $service = app(ApplicationServiceInterface::class);
        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'guardian_relation' => 'ibu',
            'create_parent_account' => true,
            'status' => 'filling_form',
        ]);

        $results = $service->createParentAccounts($application, $this->formPayload([
            'mother' => ['name' => 'Ibu', 'status' => 'Masih Hidup', 'phone' => '081234567890'],
        ]));

        $this->assertCount(1, $results);
        $this->assertStringContainsString('akun Anda sendiri', $results[0]['note']);
        $this->assertSame(0, $application->linkedUsers()->count());
    }
}
