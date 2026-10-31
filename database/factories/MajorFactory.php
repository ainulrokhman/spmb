<?php

namespace Database\Factories;

use App\Models\Major;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Major>
 */
class MajorFactory extends Factory
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
            'code' => fake()->unique()->bothify('??##'),
            'is_active' => true,
        ];
    }
}
