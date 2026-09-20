<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issuer_id' => Issuer::factory(),
            'access_key' => fake()->numerify(str_repeat('#', 44)),
            'number' => fake()->numerify('######'),
            'series' => '001',
            'issued_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'environment' => 'production',
            'total_icms_base' => fake()->randomFloat(2, 0, 100),
            'total_icms' => fake()->randomFloat(2, 0, 10),
            'total_products' => fake()->randomFloat(2, 10, 500),
            'total_amount' => fake()->randomFloat(2, 10, 500),
            'total_taxes' => fake()->randomFloat(2, 0, 50),
            'raw_xml' => '<nfeProc/>',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Pending,
            'issuer_id' => null,
            'raw_xml' => null,
            'qrcode_url' => fake()->url(),
            'total_products' => 0,
            'total_icms_base' => 0,
            'total_icms' => 0,
            'total_taxes' => 0,
        ]);
    }
}
