<?php

namespace Tests\Feature\Guardian;

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
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
     * Build a unit with an open wave, one track, one required
     * document, and one required dynamic field.
     *
     * @return array{unit: SchoolUnit, wave: AdmissionWave, track: AdmissionTrack, docType: DocumentType}
     */
    private function scenario(int $formFee = 0, ?int $quota = null): array
    {
        $year = AcademicYear::factory()->create(['name' => '2026/2027', 'is_active' => true]);
        $unit = SchoolUnit::factory()->create(['code' => '31', 'form_fee' => $formFee]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'quota' => $quota,
        ]);
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

        return ['unit' => $unit, 'wave' => $wave, 'track' => $track, 'docType' => $docType];
    }

    private function startApplication(array $scenario): Application
    {
        $response = $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), [
                'school_unit_id' => $scenario['unit']->id,
                'admission_wave_id' => $scenario['wave']->id,
                'admission_track_id' => $scenario['track']->id,
            ]);

        $application = Application::latest('id')->firstOrFail();
        $response->assertRedirect(route('guardian.applications.show', $application));

        return $application;
    }

    private function validFormPayload(): array
    {
        return [
            'child_name' => 'Anak Pertama',
            'birth_place' => 'Jakarta',
            'birth_date' => '2015-05-01',
            'gender' => 'L',
            'parent_name' => 'Orang Tua',
            'parent_phone' => '081234567890',
            'guardian_relation' => 'ayah',
            'father' => ['name' => 'Bapak', 'status' => 'Masih Hidup'],
            'mother' => ['name' => 'Ibu', 'status' => 'Masih Hidup'],
            'answers' => ['asal_sekolah' => 'SDN 01'],
        ];
    }

    public function test_free_unit_skips_payment_step(): void
    {
        $scenario = $this->scenario(formFee: 0);
        $application = $this->startApplication($scenario);

        $this->assertSame('free', $application->form_fee_status);
        $this->assertSame('filling_form', $application->status);
    }

    public function test_paid_unit_waits_at_payment_step_and_form_is_locked(): void
    {
        $scenario = $this->scenario(formFee: 50000);
        $application = $this->startApplication($scenario);

        $this->assertSame('awaiting_form_fee', $application->status);

        // The form step refuses to open before the fee is settled.
        $this->actingAs($this->guardian)
            ->get(route('guardian.applications.form', $application))
            ->assertRedirect(route('guardian.applications.show', $application));

        // Nor can the form be saved directly.
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->validFormPayload())
            ->assertSessionHas('error');
        $this->assertSame('awaiting_form_fee', $application->fresh()->status);
    }

    public function test_manual_payment_flow_unlocks_the_form(): void
    {
        $scenario = $this->scenario(formFee: 50000);
        $application = $this->startApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.pay', $application), ['channel' => 'manual'])
            ->assertRedirect(route('guardian.applications.payment', $application));

        $payment = $application->payments()->firstOrFail();
        $this->assertSame(50000, $payment->amount);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.declare-paid', [$application, $payment]));

        $this->assertSame('awaiting_confirmation', $payment->fresh()->status);
        $this->assertSame('awaiting_form_fee', $application->fresh()->status);

        // Committee confirms.
        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.confirm', $payment))
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('paid', $application->form_fee_status);
        $this->assertSame('filling_form', $application->status);
    }

    public function test_waiver_request_approved_unlocks_the_form_without_payment(): void
    {
        $scenario = $this->scenario(formFee: 50000);
        $application = $this->startApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), [
                'reason' => 'Anak yatim',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), ['decision' => 'approve', 'relief_type' => 'waiver', 'note' => 'Layak'])
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('waived', $application->form_fee_status);
        $this->assertSame('filling_form', $application->status);
    }

    public function test_discount_request_approved_reduces_the_payable_amount(): void
    {
        $scenario = $this->scenario(formFee: 50000);
        $application = $this->startApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), [
                'reason' => 'Anak karyawan',
            ]);

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), ['decision' => 'approve', 'relief_type' => 'percent', 'relief_value' => 50]);

        $application->refresh();
        $this->assertSame(25000, $application->payableAmount());
        $this->assertSame('unpaid', $application->form_fee_status);
        $this->assertSame('awaiting_form_fee', $application->status);
    }

    public function test_rejected_request_keeps_full_fee(): void
    {
        $scenario = $this->scenario(formFee: 50000);
        $application = $this->startApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.request-relief', $application), [
                'reason' => 'Mohon gratis',
            ]);

        $this->actingAs($this->admin())
            ->post(route('admin.form-payments.decide', $application), ['decision' => 'reject', 'note' => 'Belum memenuhi syarat']);

        $application->refresh();
        $this->assertSame('rejected', $application->form_fee_request_status);
        $this->assertSame(50000, $application->payableAmount());
    }

    public function test_full_flow_issues_sequential_registration_numbers(): void
    {
        Storage::fake('public');
        $scenario = $this->scenario(formFee: 0);

        $numbers = [];
        foreach (['Anak Satu', 'Anak Dua'] as $name) {
            $application = $this->startApplication($scenario);

            $payload = $this->validFormPayload();
            $payload['child_name'] = $name;
            $this->actingAs($this->guardian)
                ->post(route('guardian.applications.form.save', $application), $payload)
                ->assertRedirect(route('guardian.applications.documents', $application));

            $this->actingAs($this->guardian)
                ->post(route('guardian.applications.documents.upload', [$application, $scenario['docType']]), [
                    'file' => UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf'),
                ])
                ->assertSessionHas('success');

            $this->actingAs($this->guardian)
                ->post(route('guardian.applications.submit', $application))
                ->assertRedirect(route('guardian.applications.proof', $application));

            $numbers[] = $application->fresh()->registration_number;
        }

        $this->assertSame(['3126001', '3126002'], $numbers);
    }

    public function test_submit_is_blocked_until_required_documents_are_uploaded(): void
    {
        $scenario = $this->scenario(formFee: 0);
        $application = $this->startApplication($scenario);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $application), $this->validFormPayload());

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.submit', $application))
            ->assertSessionHas('error');

        $this->assertNull($application->fresh()->registration_number);
    }

    public function test_submit_is_blocked_when_wave_quota_is_full(): void
    {
        Storage::fake('public');
        $scenario = $this->scenario(formFee: 0, quota: 1);

        $first = $this->startApplication($scenario);
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $first), $this->validFormPayload());
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.documents.upload', [$first, $scenario['docType']]), [
                'file' => UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf'),
            ]);
        $this->actingAs($this->guardian)->post(route('guardian.applications.submit', $first));
        $this->assertNotNull($first->fresh()->registration_number);

        $second = $this->startApplication($scenario);
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.form.save', $second), $this->validFormPayload());
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.documents.upload', [$second, $scenario['docType']]), [
                'file' => UploadedFile::fake()->create('kk2.pdf', 100, 'application/pdf'),
            ]);
        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.submit', $second))
            ->assertSessionHas('error');
        $this->assertNull($second->fresh()->registration_number);
    }

    public function test_guardian_cannot_open_another_guardians_application(): void
    {
        $scenario = $this->scenario(formFee: 0);
        $application = $this->startApplication($scenario);

        $other = User::factory()->create(['phone' => '089999999999']);
        $other->assignRole('Wali');

        $this->actingAs($other)
            ->get(route('guardian.applications.show', $application))
            ->assertNotFound();
    }

    public function test_start_rejects_a_track_the_wave_does_not_open(): void
    {
        $scenario = $this->scenario(formFee: 0);
        $otherTrack = AdmissionTrack::factory()->create(['school_unit_id' => $scenario['unit']->id]);

        $this->actingAs($this->guardian)
            ->post(route('guardian.applications.store'), [
                'school_unit_id' => $scenario['unit']->id,
                'admission_wave_id' => $scenario['wave']->id,
                'admission_track_id' => $otherTrack->id,
            ])
            ->assertSessionHasErrors('admission_track_id');

        $this->assertDatabaseCount('applications', 0);
    }
}
