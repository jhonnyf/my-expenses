<?php

namespace Database\Factories;

use App\Models\QrCodeRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCodeRead>
 */
class QrCodeReadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'qrcode_url' => fake()->url(),
            'status' => 'success',
            'error_message' => null,
            'invoice_id' => null,
        ];
    }
}
