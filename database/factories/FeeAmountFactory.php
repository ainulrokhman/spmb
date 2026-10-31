<?php

namespace Database\Factories;

use App\Models\Fee;
use App\Models\FeeAmount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeAmount>
 */
class FeeAmountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_id' => Fee::factory(),
            'admission_track_id' => null,
            'gender' => null,
            'amount' => fake()->numberBetween(1, 200) * 10000,
            'sort_order' => 0,
        ];
    }
}
