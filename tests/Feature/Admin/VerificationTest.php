<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class VerificationTest extends TestCase
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
     * Build a submitted application on a fresh unit with one
     * required document already uploaded.
     *
     * @return array{application: Application, document: ApplicationDocument, unit: SchoolUnit, docType: DocumentType}
     */
    private function submittedApplication(string $unitName = 'Unit Uji', string $unitCode = '31'): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => $unitName, 'code' => $unitCode]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'quota' => null,
        ]);
        $wave->admissionTracks()->attach($track);

        $docType = DocumentType::factory()->create(['code' => 'kk-'.$unit->id, 'name' => 'Kartu Keluarga '.$unitCode]);
        DocumentRequirement::create([
            'school_unit_id' => $unit->id,
            'document_type_id' => $docType->id,
            'requirement' => DocumentRequirement::REQUIRED,
        ]);

        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $year->id,
            'child_name' => 'Anak Terdaftar',
            'status' => 'submitted',
            'registration_number' => $unitCode.'26001',
            'submitted_at' => now(),
        ]);

        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'document_type_id' => $docType->id,
        ]);

        return ['application' => $application, 'document' => $document, 'unit' => $unit, 'docType' => $docType];
    }

    /**
     * A committee member limited to one unit.
     */
    private function committeeMember(SchoolUnit $unit): User
    {
        $role = Role::firstOrCreate(['name' => 'PanitiaVerifikasi']);
        $role->syncPermissions(['application-list', 'application-verify']);

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->schoolUnits()->attach($unit);

        return $user;
    }

    public function test_admin_sees_submitted_applications_in_the_list(): void
    {
        $this->submittedApplication();

        $response = $this->actingAs($this->admin())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.applications.index'));

        $response->assertOk();
        $response->assertSee('3126001', false);
        $response->assertSee('Anak Terdaftar', false);
    }

    public function test_scoped_committee_only_sees_own_unit(): void
    {
        $own = $this->submittedApplication('Unit Sendiri');
        $this->submittedApplication('Unit Orang', '32');

        $response = $this->actingAs($this->committeeMember($own['unit']))
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.applications.index'));

        $response->assertOk();
        $response->assertSee('Unit Sendiri', false);
        $response->assertDontSee('Unit Orang', false);
    }

    public function test_scoped_committee_cannot_open_other_units_detail(): void
    {
        $own = $this->submittedApplication('Unit Sendiri');
        $foreign = $this->submittedApplication('Unit Orang', '32');

        $this->actingAs($this->committeeMember($own['unit']))
            ->get(route('admin.applications.show', $foreign['application']))
            ->assertForbidden();
    }

    public function test_document_verification_and_overall_decision(): void
    {
        ['application' => $application, 'document' => $document] = $this->submittedApplication();

        $this->actingAs($this->admin())
            ->post(route('admin.applications.documents.verify', [$application, $document]), ['status' => 'verified'])
            ->assertSessionHas('success');

        $this->assertSame('verified', $document->fresh()->verification_status);

        $this->actingAs($this->admin())
            ->post(route('admin.applications.decide', $application), ['decision' => 'verified'])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('verified', $application->status);
        $this->assertNotNull($application->verified_by);
    }

    public function test_overall_verified_is_blocked_until_required_documents_are_verified(): void
    {
        ['application' => $application] = $this->submittedApplication();

        $this->actingAs($this->admin())
            ->post(route('admin.applications.decide', $application), ['decision' => 'verified'])
            ->assertSessionHas('error');

        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_revision_status_requires_a_note(): void
    {
        ['application' => $application, 'document' => $document] = $this->submittedApplication();

        $this->actingAs($this->admin())
            ->post(route('admin.applications.documents.verify', [$application, $document]), ['status' => 'revision'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $document->fresh()->verification_status);
    }

    public function test_needs_revision_loop_guardian_fixes_and_resubmits_with_same_number(): void
    {
        Storage::fake('public');
        ['application' => $application, 'document' => $document, 'docType' => $docType] = $this->submittedApplication();

        $this->actingAs($this->admin())
            ->post(route('admin.applications.documents.verify', [$application, $document]), [
                'status' => 'revision',
                'note' => 'KK buram, unggah ulang yang jelas.',
            ]);

        $this->actingAs($this->admin())
            ->post(route('admin.applications.decide', $application), [
                'decision' => 'needs_revision',
                'note' => 'Perbaiki berkas KK.',
            ])
            ->assertSessionHas('success');

        $this->assertSame('needs_revision', $application->fresh()->status);

        // The guardian sees the note on the dashboard.
        $this->actingAs($this->guardian)
            ->get(route('guardian.dashboard'))
            ->assertSee('Perbaiki berkas KK.');

        // The guardian replaces the document; its check restarts.
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.documents.upload', [$application, $docType]), [
                'file' => UploadedFile::fake()->create('kk-baru.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $this->assertSame('pending', $document->fresh()->verification_status);

        // Resubmission keeps the same registration number.
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.submit', $application))
            ->assertRedirect(route('guardian.applications.proof', $application));

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('3126001', $application->registration_number);
    }

    public function test_rejection_records_the_reason(): void
    {
        ['application' => $application] = $this->submittedApplication();

        $this->actingAs($this->admin())
            ->post(route('admin.applications.decide', $application), [
                'decision' => 'rejected',
                'note' => 'Di luar zonasi yayasan.',
            ])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame('Di luar zonasi yayasan.', $application->verification_note);
    }

    public function test_user_without_permission_cannot_open_verification_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->get(route('admin.applications.index'))
            ->assertForbidden();
    }
}
