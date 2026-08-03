<?php

namespace Database\Factories;

use App\Models\FavoriteProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FavoriteProduct>
 */
class FavoriteProductFactory extends Factory
{
    protected $model = FavoriteProduct::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'canonical_name' => fake()->words(3, true),
            'unit' => 'UN',
            'last_notified_price' => null,
            'last_notified_at' => null,
        ];
    }
}
