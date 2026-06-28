<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $qty   = fake()->randomFloat(4, 1, 10);
        $price = fake()->randomFloat(4, 1, 100);

        return [
            'invoice_id'  => Invoice::factory(),
            'item_number' => fake()->numberBetween(1, 50),
            'code'        => fake()->numerify('######'),
            'description' => strtoupper(fake()->words(3, true)),
            'ncm'         => fake()->numerify('########'),
            'cfop'        => '5102',
            'unit'        => 'UN',
            'quantity'    => $qty,
            'unit_price'  => $price,
            'total_price' => round($qty * $price, 2),
            'category_id' => null,
        ];
    }
}
