<?php

namespace Database\Factories;

use App\Models\InvoiceInstallment;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InvoiceInstallmentItem>
 */
class InvoiceInstallmentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_installment_id' => InvoiceInstallment::factory(),
            'invoice_item_id' => InvoiceItem::factory(),
            'amount' => 100000,
        ];
    }
}
