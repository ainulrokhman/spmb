<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\Fee;
use App\Models\FeeComponent;
use App\Models\FormField;
use App\Models\Major;
use App\Models\Program;
use App\Models\SchoolUnit;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Complete starting setup for SMA Al-Hikmah 2, Sirampog, Brebes:
 * branding, the school as one unit, its four majors with the
 * quotas published on the school's own page (Tahfidz 12 putra /
 * 13 putri, Sains 30, Kitab Kuning 30, Reguler without a quota),
 * one admission track and wave, document requirements, dynamic
 * form fields, and the daftar-ulang fee structure.
 *
 * Run explicitly:  php artisan db:seed --class=AlHikmahSeeder
 *
 * Production-safe and idempotent: it writes master data only
 * (no accounts, no applications) and never overwrites branding
 * that is already set. Two things are deliberately NOT final and
 * are printed as warnings at the end of the run: the form fee
 * (Rp 200.000, taken from the regular wave of the old system's
 * seed data — the old system varied it per wave, SPMB charges
 * it per unit) and the daftar-ulang fee amounts (the structure
 * is created, the amounts stay empty for the school to fill in
 * under Admin → Biaya before the wave opens).
 */
class AlHikmahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SpmbMasterSeeder::class,
            WhatsappTemplateSeeder::class,
        ]);

        $this->seedBranding();

        $year = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['is_active' => true]
        );

        $unit = $this->seedUnit();
        $this->seedMajors($unit);
        $track = $this->seedTrack($unit);
        $this->seedWave($unit, $year, $track);
        $this->seedDocumentRequirements($unit);
        $this->seedFormFields($unit);
        $this->seedFeeStructure($unit);

        $this->command?->warn('AlHikmahSeeder selesai. Periksa sebelum gelombang dibuka: (1) biaya formulir unit Rp 200.000 (dari gelombang reguler sistem lama — pastikan nominalnya), (2) tanggal Gelombang 1 (dibuka hari ini, ditutup 90 hari lagi), (3) nominal SPI/SOP/DU di Admin → Biaya (struktur dibuat, nominal sengaja dikosongkan).');
    }

    /**
     * Foundation identity, only where it is not set yet.
     */
    private function seedBranding(): void
    {
        if (! Setting::get('foundation_name')) {
            Setting::set('foundation_name', 'SMA Al-Hikmah 2 Sirampog');
        }

        if (! Setting::get('foundation_tagline')) {
            Setting::set('foundation_tagline', 'Sekolah dengan program unggulan Tahfidz Al-Qur’an, Sains, dan Kitab Kuning di Sirampog, Brebes.');
        }
    }

    /**
     * The school itself as the foundation's single unit. Its code
     * (21) leads every registration number, e.g. 2126001.
     */
    private function seedUnit(): SchoolUnit
    {
        $reguler = Program::where('name', 'Reguler')->firstOrFail();

        return SchoolUnit::firstOrCreate(
            ['code' => '21'],
            [
                'name' => 'SMA Al-Hikmah 2',
                'level' => 'SMA/MA',
                'program_id' => $reguler->id,
                'form_fee' => 200000,
                'selection_enabled' => true,
                'address' => 'Sirampog, Brebes, Jawa Tengah',
                'is_active' => true,
            ]
        );
    }

    /**
     * The school's classes as majors; quotas follow the numbers
     * the school publishes for each class.
     */
    private function seedMajors(SchoolUnit $unit): void
    {
        $definitions = [
            ['name' => 'Tahfidz', 'quota' => 25, 'quota_putra' => 12, 'quota_putri' => 13],
            ['name' => 'Sains', 'quota' => 30, 'quota_putra' => null, 'quota_putri' => null],
            ['name' => 'Kitab Kuning', 'quota' => 30, 'quota_putra' => null, 'quota_putri' => null],
            ['name' => 'Reguler', 'quota' => null, 'quota_putra' => null, 'quota_putri' => null],
        ];

        foreach ($definitions as $definition) {
            Major::updateOrCreate(
                ['school_unit_id' => $unit->id, 'name' => $definition['name']],
                array_merge($definition, ['is_active' => true])
            );
        }
    }

    /**
     * The one admission track the school opens with.
     */
    private function seedTrack(SchoolUnit $unit): AdmissionTrack
    {
        return AdmissionTrack::firstOrCreate(
            ['school_unit_id' => $unit->id, 'name' => 'Reguler'],
            ['is_active' => true, 'is_transfer' => false]
        );
    }

    /**
     * The first wave: open from today for 90 days, no wave-level
     * quota (the major quotas are the binding limits).
     */
    private function seedWave(SchoolUnit $unit, AcademicYear $year, AdmissionTrack $track): void
    {
        $wave = AdmissionWave::firstOrCreate(
            ['school_unit_id' => $unit->id, 'academic_year_id' => $year->id, 'name' => 'Gelombang 1'],
            ['opens_at' => now()->startOfDay(), 'closes_at' => now()->addDays(90)->endOfDay(), 'is_active' => true]
        );
        $wave->admissionTracks()->syncWithoutDetaching([$track->id]);
    }

    /**
     * The standard document matrix for an SMA intake.
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
     * Dynamic fields beyond the core biodata form.
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
     * The daftar-ulang fee structure for the unit. Amounts stay
     * empty on purpose; INFAQ stays free-form (bebas).
     */
    private function seedFeeStructure(SchoolUnit $unit): void
    {
        foreach (FeeComponent::all() as $component) {
            Fee::updateOrCreate(
                ['school_unit_id' => $unit->id, 'fee_component_id' => $component->id],
                ['mode' => $component->code === 'INFAQ' ? Fee::MODE_BEBAS : Fee::MODE_FIXED, 'min_amount' => null]
            );
        }
    }
}
