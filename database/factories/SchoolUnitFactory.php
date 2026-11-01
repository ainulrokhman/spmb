<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolUnit>
 */
class SchoolUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' School',
            'code' => fake()->unique()->numerify('##'),
            'level' => fake()->randomElement(SchoolUnit::LEVELS),
            'program_id' => Program::factory(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
