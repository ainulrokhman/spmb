<?php

namespace Database\Factories;

use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequirement>
 */
class DocumentRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_unit_id' => SchoolUnit::factory(),
            'document_type_id' => DocumentType::factory(),
            'requirement' => fake()->randomElement(DocumentRequirement::LEVELS),
        ];
    }
}
