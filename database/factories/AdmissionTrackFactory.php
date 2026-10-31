<?php

namespace Database\Factories;

use App\Models\AdmissionTrack;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionTrack>
 */
class AdmissionTrackFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
