<?php

namespace Database\Seeders;

use App\Contracts\ApplicationServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\FormField;
use App\Models\Major;
use App\Models\Program;
use App\Models\SchoolUnit;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\SystemAccess;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Complete demo dataset for testers: school units across levels,
 * tracks, waves, document requirements, dynamic form fields, fee
 * mappings, committee members with unit assignments, guardian
 * accounts, and sample applications at several flow stages.
 *
 * Run explicitly:  php artisan db:seed --class=DemoSeeder
 *
 * NEVER in production — this seeder refuses to run when
 * APP_ENV=production, and every account it creates uses the
 * well-known password "password". All records are idempotent
 * (firstOrCreate/updateOrCreate), so re-running is safe.
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (config('app.env') === 'production') {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di production.');
        }

        $this->call([
            RolesAndPermissionsSeeder::class,
            SpmbMasterSeeder::class,
            DemoUserSeeder::class,
        ]);

        $year = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['is_active' => true]
        );

        $units = $this->seedUnits();
        $waves = [];

        foreach ($units as $code => $unit) {
            $tracks = $this->seedTracks($unit);
            $waves[$code] = $this->seedWave($unit, $year, $tracks, 'Gelombang 1', now()->subDays(7), now()->addDays(30));
            $this->seedDocumentRequirements($unit);
            $this->seedFormFields($unit);
            $this->seedFees($unit, $code);
        }

        // An upcoming second wave for the SMK unit.
        $this->seedWave($units['42'], $year, $units['42']->admissionTracks()->get(), 'Gelombang 2', now()->addDays(31), now()->addDays(60));

        $this->seedMajors($units['42']);
        $this->seedCommittee($units);
        $guardians = $this->seedGuardians();
        $this->seedApplications($guardians, $units, $waves);

        $this->command?->info('DemoSeeder selesai. Akun demo (password: password): superadmin@example.com, admin@example.com, panitia.smp@example.com, panitia.smk@example.com, wali HP 081200000001 s.d. 081200000003.');
    }

    /**
     * Three sample units across levels and programs.
     *
     * @return array<string, SchoolUnit>
     */
    private function seedUnits(): array
    {
        $reguler = Program::where('name', 'Reguler')->firstOrFail();
        $boarding = Program::where('name', 'Boarding')->firstOrFail();

        $definitions = [
            '11' => ['name' => 'PAUD Contoh Melati', 'level' => 'PAUD/TK', 'program_id' => $reguler->id, 'form_fee' => 0],
            '31' => ['name' => 'SMP Contoh Nusantara', 'level' => 'SMP/MTs', 'program_id' => $reguler->id, 'form_fee' => 50000],
            '42' => ['name' => 'SMK Contoh Teknologi', 'level' => 'SMK', 'program_id' => $boarding->id, 'form_fee' => 100000],
        ];

        $units = [];

        foreach ($definitions as $code => $attributes) {
            $units[$code] = SchoolUnit::firstOrCreate(
                ['code' => $code],
                array_merge($attributes, ['is_active' => true])
            );
        }

        return $units;
    }

    /**
     * The standard tracks every demo unit offers.
     *
     * @return Collection<int, AdmissionTrack>
     */
    private function seedTracks(SchoolUnit $unit)
    {
        foreach (['Reguler', 'Prestasi', 'Afirmasi'] as $name) {
            AdmissionTrack::firstOrCreate(
                ['school_unit_id' => $unit->id, 'name' => $name],
                ['is_active' => true]
            );
        }

        return $unit->admissionTracks()->get();
    }

    /**
     * One wave with all of the unit's tracks attached.
     *
     * @param  Collection<int, AdmissionTrack>  $tracks
     */
    private function seedWave(SchoolUnit $unit, AcademicYear $year, $tracks, string $name, $opensAt, $closesAt): AdmissionWave
    {
        $wave = AdmissionWave::firstOrCreate(
            ['school_unit_id' => $unit->id, 'academic_year_id' => $year->id, 'name' => $name],
            ['opens_at' => $opensAt, 'closes_at' => $closesAt, 'is_active' => true]
        );
        $wave->admissionTracks()->syncWithoutDetaching($tracks->pluck('id'));

        return $wave;
    }

    /**
     * Majors for the vocational (SMK) unit.
     */
    private function seedMajors(SchoolUnit $unit): void
    {
        foreach (['Rekayasa Perangkat Lunak', 'Akuntansi'] as $name) {
            Major::firstOrCreate(
                ['school_unit_id' => $unit->id, 'name' => $name],
                ['is_active' => true]
            );
        }
    }

    /**
     * A representative requirement matrix per unit.
     */
    private function seedDocumentRequirements(SchoolUnit $unit): void
    {
        $matrix = [
            'kartu-keluarga' => DocumentRequirement::REQUIRED,
            'akta-kelahiran' => DocumentRequirement::REQUIRED,
            'pas-foto' => DocumentRequirement::REQUIRED,
            'ijazah-skl' => DocumentRequirement::OPTIONAL,
            'rapor' => DocumentRequirement::OPTIONAL,
        ];

        foreach ($matrix as $code => $requirement) {
            $type = DocumentType::where('code', $code)->first();

            if ($type === null) {
                continue;
            }

            DocumentRequirement::updateOrCreate(
                ['school_unit_id' => $unit->id, 'document_type_id' => $type->id],
                ['requirement' => $requirement]
            );
        }
    }

    /**
     * Sample dynamic fields exercising several field types.
     */
    private function seedFormFields(SchoolUnit $unit): void
    {
        $fields = [
            ['key' => 'asal_sekolah', 'label' => 'Asal Sekolah', 'type' => 'text', 'is_required' => true, 'section' => 'Data Tambahan', 'sort_order' => 1],
            ['key' => 'ukuran_seragam', 'label' => 'Ukuran Seragam', 'type' => 'select', 'options' => ['S', 'M', 'L', 'XL'], 'is_required' => false, 'section' => 'Data Tambahan', 'sort_order' => 2],
            ['key' => 'golongan_darah', 'label' => 'Golongan Darah', 'type' => 'radio', 'options' => ['A', 'B', 'AB', 'O'], 'is_required' => false, 'section' => 'Data Tambahan', 'sort_order' => 3],
            ['key' => 'catatan_kesehatan', 'label' => 'Catatan Kesehatan', 'type' => 'textarea', 'is_required' => false, 'section' => 'Data Tambahan', 'sort_order' => 4],
        ];

        foreach ($fields as $field) {
            FormField::updateOrCreate(
                ['school_unit_id' => $unit->id, 'key' => $field['key']],
                array_merge($field, ['is_active' => true])
            );
        }
    }

    /**
     * Fee mappings with global amounts per unit tier; INFAQ stays
     * free-form (bebas) per the design decision.
     */
    private function seedFees(SchoolUnit $unit, string $code): void
    {
        $amounts = [
            '11' => ['SPI' => 500000, 'SOP' => 300000, 'DU' => 200000],
            '31' => ['SPI' => 1500000, 'SOP' => 750000, 'DU' => 350000],
            '42' => ['SPI' => 2500000, 'SOP' => 1000000, 'DU' => 500000],
        ];

        foreach (FeeComponent::all() as $component) {
            $mode = $component->code === 'INFAQ' ? Fee::MODE_BEBAS : Fee::MODE_FIXED;

            $fee = Fee::updateOrCreate(
                ['school_unit_id' => $unit->id, 'fee_component_id' => $component->id],
                ['mode' => $mode, 'min_amount' => null]
            );

            if ($mode === Fee::MODE_FIXED && isset($amounts[$code][$component->code])) {
                FeeAmount::updateOrCreate(
                    ['fee_id' => $fee->id, 'admission_track_id' => null, 'gender' => null],
                    ['amount' => $amounts[$code][$component->code]]
                );
            }
        }
    }

    /**
     * One committee member per paid unit, assigned to that unit.
     *
     * @param  array<string, SchoolUnit>  $units
     */
    private function seedCommittee(array $units): void
    {
        $assignments = [
            'panitia.smp@example.com' => ['Panitia SMP Demo', $units['31']],
            'panitia.smk@example.com' => ['Panitia SMK Demo', $units['42']],
        ];

        foreach ($assignments as $email => [$name, $unit]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')]
            );
            $user->assignRole(SystemAccess::PANITIA_ROLE);
            $user->schoolUnits()->syncWithoutDetaching([$unit->id]);
        }
    }

    /**
     * Three guardian accounts identified by phone number.
     *
     * @return array<int, User>
     */
    private function seedGuardians(): array
    {
        $guardians = [];

        foreach (['081200000001' => 'Wali Demo Satu', '081200000002' => 'Wali Demo Dua', '081200000003' => 'Wali Demo Tiga'] as $phone => $name) {
            $user = User::firstOrCreate(
                ['phone' => $phone],
                ['name' => $name, 'password' => Hash::make('password')]
            );
            $user->assignRole(SystemAccess::WALI_ROLE);
            $guardians[] = $user;
        }

        return $guardians;
    }

    /**
     * Sample applications at four different flow stages, built
     * through the real services so every state is consistent.
     *
     * @param  array<int, User>  $guardians
     * @param  array<string, SchoolUnit>  $units
     * @param  array<string, AdmissionWave>  $waves
     */
    private function seedApplications(array $guardians, array $units, array $waves): void
    {
        $service = app(ApplicationServiceInterface::class);
        $payments = app(PaymentService::class);

        [$wali1, $wali2, $wali3] = $guardians;

        // (a) Waiting at the form fee step (SMP, Rp 50.000).
        $this->findOrStart($service, $wali1, $waves['31']);

        // (b) Relief request (reason only) pending a committee decision (SMP).
        $relief = $this->findOrStart($service, $wali2, $waves['31']);
        if ($relief->form_fee_request_status !== 'pending') {
            $service->requestFeeRelief($relief, 'Anak yatim; surat keterangan dari kelurahan terlampir.');
        }

        // (c) Fee paid manually and confirmed; ready to fill the form (SMK).
        $paid = $this->findOrStart($service, $wali3, $waves['42']);
        if (! $paid->feeSettled()) {
            $result = $payments->startPayment($paid, 'manual');
            $payments->declareManualPaid($result['payment']);
            $payments->confirmPayment($result['payment']);
        }

        // (d) Fully submitted on the free PAUD unit, documents and
        // registration number included.
        $submitted = $this->findOrStart($service, $wali1, $waves['11']);
        if ($submitted->registration_number === null) {
            $service->saveForm($submitted, [
                'child_name' => 'Bima Demo',
                'birth_place' => 'Yogyakarta',
                'birth_date' => '2020-04-12',
                'gender' => 'L',
                'parent_name' => 'Wali Demo Satu',
                'parent_phone' => '081200000001',
                'guardian_relation' => 'ayah',
                'father' => ['name' => 'Wali Demo Satu', 'status' => 'Masih Hidup', 'job' => 'Wiraswasta'],
                'mother' => ['name' => 'Ibu Demo Satu', 'status' => 'Masih Hidup', 'job' => 'Guru'],
                'has_guardian' => false,
                'answers' => ['asal_sekolah' => 'TK Melati'],
            ]);

            foreach ($service->missingRequiredDocuments($submitted) as $type) {
                $service->storeDocument($submitted, $type, $this->samplePdf($type->code));
            }

            $service->submit($submitted);
        }
    }

    /**
     * Find the guardian's existing application on the wave, or start
     * a new one on the wave's first track.
     */
    private function findOrStart(ApplicationServiceInterface $service, User $guardian, AdmissionWave $wave): Application
    {
        $existing = Application::query()
            ->where('user_id', $guardian->id)
            ->where('admission_wave_id', $wave->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $wave->loadMissing('admissionTracks');

        return $service->start($guardian, $wave, $wave->admissionTracks->firstOrFail(), null);
    }

    /**
     * A small but valid PDF as an UploadedFile for sample documents.
     */
    private function samplePdf(string $name): UploadedFile
    {
        $directory = storage_path('app/demo-seed');
        $path = $directory.'/'.$name.'.pdf';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! file_exists($path)) {
            file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        }

        return new UploadedFile($path, $name.'.pdf', 'application/pdf', null, true);
    }
}
