<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\AdmissionWave;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionWave>
 */
class AdmissionWaveFactory extends Factory
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
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Gelombang '.fake()->numberBetween(1, 3),
            'quota' => fake()->optional()->numberBetween(10, 200),
            'opens_at' => now()->subDays(3),
            'closes_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}
