<?php

namespace Database\Factories;

use App\Models\IssuerNickname;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssuerNickname>
 */
class IssuerNicknameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nickname' => fake()->word(),
        ];
    }
}
