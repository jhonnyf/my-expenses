<?php

namespace Database\Factories;

use App\Models\Issuer;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingListItem>
 */
class ShoppingListItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'issuer_id'        => Issuer::factory(),
            'description'      => strtoupper(fake()->words(2, true)),
            'unit'             => 'UN',
            'unit_price'       => fake()->randomFloat(2, 1, 50),
            'quantity'         => 1,
            'purchased_at'     => null,
        ];
    }
}
