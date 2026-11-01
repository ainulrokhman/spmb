<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'document_type_id' => DocumentType::factory(),
            'file_path' => 'berkas-pendaftaran/1/contoh.pdf',
            'original_name' => 'contoh.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
