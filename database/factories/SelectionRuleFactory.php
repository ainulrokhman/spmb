<?php

namespace Database\Factories;

use App\Models\SchoolUnit;
use App\Models\SelectionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SelectionRule>
 */
class SelectionRuleFactory extends Factory
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
            'kind' => SelectionRule::KIND_MANUAL,
            'name' => 'Kriteria '.$this->faker->word(),
            'config' => ['max_points' => 20],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
