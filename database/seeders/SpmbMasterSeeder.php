<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\FeeComponent;
use Illuminate\Database\Seeder;

class SpmbMasterSeeder extends Seeder
{
    /**
     * Standard document types shared by all units.
     *
     * Units decide per type whether it is required, optional, or not
     * used at all through their document requirements.
     */
    public function run(): void
    {
        $documentTypes = [
            ['name' => 'Pas Foto', 'code' => 'pas-foto', 'allowed_extensions' => 'jpg,jpeg,png', 'max_size_kb' => 1024],
            ['name' => 'Kartu Keluarga', 'code' => 'kartu-keluarga', 'allowed_extensions' => 'jpg,jpeg,png,pdf', 'max_size_kb' => 2048],
            ['name' => 'Akta Kelahiran', 'code' => 'akta-kelahiran', 'allowed_extensions' => 'jpg,jpeg,png,pdf', 'max_size_kb' => 2048],
            ['name' => 'Ijazah/SKL', 'code' => 'ijazah-skl', 'allowed_extensions' => 'jpg,jpeg,png,pdf', 'max_size_kb' => 2048],
            ['name' => 'Rapor', 'code' => 'rapor', 'allowed_extensions' => 'jpg,jpeg,png,pdf', 'max_size_kb' => 5120],
            ['name' => 'Sertifikat Prestasi', 'code' => 'sertifikat-prestasi', 'allowed_extensions' => 'jpg,jpeg,png,pdf', 'max_size_kb' => 2048],
        ];

        foreach ($documentTypes as $documentType) {
            DocumentType::firstOrCreate(
                ['code' => $documentType['code']],
                array_merge($documentType, ['is_active' => true])
            );
        }

        $feeComponents = [
            ['code' => 'SPI', 'name' => 'Sumbangan Pengembangan Institusi'],
            ['code' => 'SOP', 'name' => 'Sumbangan Operasional Pendidikan'],
            ['code' => 'DU', 'name' => 'Daftar Ulang'],
            ['code' => 'INFAQ', 'name' => 'Infaq'],
        ];

        foreach ($feeComponents as $feeComponent) {
            FeeComponent::firstOrCreate(
                ['code' => $feeComponent['code']],
                array_merge($feeComponent, ['is_active' => true])
            );
        }
    }
}
