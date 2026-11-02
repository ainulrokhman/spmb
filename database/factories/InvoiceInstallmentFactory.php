<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceInstallment>
 */
class InvoiceInstallmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'sequence' => 1,
            'amount' => 250000,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
        ];
    }
}
