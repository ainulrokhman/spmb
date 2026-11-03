<?php

namespace Tests\Feature\Guardian;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\FormField;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * The guardian's read-only Rincian Pendaftaran page: all
 * submitted data in one place plus the Jadwal Penting
 * (wave dates, announcement time, installment due dates),
 * and the nearest schedule on the dashboard.
 */
class ApplicationDetailTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        $this->guardian = User::factory()->create(['phone' => '081200007777']);
        $this->guardian->assignRole('Wali');

        $unit = SchoolUnit::factory()->create(['name' => 'SMP Rincian']);
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->addDays(20),
            'announcement_at' => now()->addDays(5),
        ]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);

        FormField::factory()->create([
            'school_unit_id' => $unit->id,
            'key' => 'hobi',
            'label' => 'Hobi Anak',
            'type' => 'text',
        ]);

        $this->application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'child_name' => 'Anak Rincian',
            'birth_place' => 'Yogyakarta',
            'birth_date' => '2014-05-01',
            'gender' => 'L',
            'status' => 'submitted',
            'registration_number' => '0326001',
            'submitted_at' => now()->subDay(),
            'guardian_relation' => 'ayah',
            'father_data' => ['nama' => 'Bapak Rincian', 'pekerjaan' => 'Petani'],
            'answers' => ['hobi' => 'Membaca'],
            'form_fee_amount' => 50000,
            'form_fee_status' => 'paid',
        ]);

        $this->application->payments()->create([
            'channel' => 'manual',
            'amount' => 50000,
            'status' => 'paid',
            'reference' => 'FRM-DETAIL-1',
            'paid_at' => now(),
        ]);

        $documentType = DocumentType::factory()->create(['name' => 'Kartu Keluarga']);
        ApplicationDocument::factory()->create([
            'application_id' => $this->application->id,
            'document_type_id' => $documentType->id,
            'original_name' => 'kk-scan.pdf',
            'verification_status' => 'verified',
        ]);

        $invoice = Invoice::factory()->create(['application_id' => $this->application->id, 'status' => 'unpaid']);
        InvoiceInstallment::factory()->create([
            'invoice_id' => $invoice->id,
            'sequence' => 1,
            'amount' => 250000,
            'due_date' => now()->addDays(10),
            'status' => 'unpaid',
        ]);
    }

    public function test_owner_sees_all_submitted_data_and_schedules(): void
    {
        $response = $this->actingAs($this->guardian)
            ->get(route('guardian.applications.detail', $this->application));

        $response->assertOk();
        // Submitted data.
        $response->assertSee('Anak Rincian');
        $response->assertSee('Yogyakarta');
        $response->assertSee('Bapak Rincian');
        $response->assertSee('Hobi Anak');
        $response->assertSee('Membaca');
        $response->assertSee('Kartu Keluarga');
        $response->assertSee('kk-scan.pdf');
        $response->assertSee('Terverifikasi');
        $response->assertSee('FRM-DETAIL-1');
        // Schedules.
        $response->assertSee('Jadwal Penting');
        $response->assertSee('Pendaftaran gelombang ditutup');
        $response->assertSee('Pengumuman hasil');
        $response->assertSee(now()->addDays(5)->format('d M Y'));
        $response->assertSee('Jatuh tempo termin 1');
        $response->assertSee('Rp 250.000');
    }

    public function test_result_stays_masked_until_the_announcement_time(): void
    {
        $this->application->update(['status' => 'passed']);

        $response = $this->actingAs($this->guardian)
            ->get(route('guardian.applications.detail', $this->application));

        $response->assertOk();
        $response->assertSee('Menunggu Pengumuman');
        $response->assertSee('Hasil baru terlihat pada waktu ini');
    }

    public function test_linked_parent_can_open_but_stranger_cannot(): void
    {
        $parent = User::factory()->create(['phone' => '081200008888']);
        $parent->assignRole('Wali');
        $this->application->linkedUsers()->attach($parent->id, ['relation' => 'ibu']);

        $this->actingAs($parent)
            ->get(route('guardian.applications.detail', $this->application))
            ->assertOk();

        $stranger = User::factory()->create(['phone' => '081200009999']);
        $stranger->assignRole('Wali');

        $this->actingAs($stranger)
            ->get(route('guardian.applications.detail', $this->application))
            ->assertNotFound();
    }

    public function test_dashboard_shows_the_nearest_schedule_and_detail_link(): void
    {
        $response = $this->actingAs($this->guardian)
            ->get(route('guardian.dashboard'));

        $response->assertOk();
        // The announcement (+5 days) is nearer than the
        // installment due date (+10 days).
        $response->assertSee('Jadwal terdekat');
        $response->assertSee('Pengumuman hasil');
        $response->assertSee('Lihat Rincian');
    }
}
