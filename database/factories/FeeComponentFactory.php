<?php

namespace Database\Factories;

use App\Models\FeeComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeComponent>
 */
class FeeComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->words(2, true),
            'description' => null,
            'is_active' => true,
        ];
    }
}
