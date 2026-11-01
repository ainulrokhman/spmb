<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => Application::class,
            'payable_id' => Application::factory(),
            'channel' => 'manual',
            'amount' => 50000,
            'status' => 'pending',
            'reference' => 'PAY-'.fake()->unique()->numerify('######'),
        ];
    }
}
