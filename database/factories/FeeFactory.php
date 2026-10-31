<?php

namespace Database\Factories;

use App\Models\Fee;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fee>
 */
class FeeFactory extends Factory
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
            'fee_component_id' => FeeComponent::factory(),
            'mode' => fake()->randomElement(Fee::MODES),
            'min_amount' => null,
        ];
    }
}
