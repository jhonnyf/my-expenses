<?php

namespace Tests\Feature;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/shopping-list')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/shopping-list')
            ->assertStatus(200);
    }

    public function test_index_lists_only_current_user_lists_with_items_total(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'unit_price' => 10, 'quantity' => 2]);
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'unit_price' => 5, 'quantity' => 1]);

        ShoppingList::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get('/shopping-list')
            ->assertStatus(200)
            ->assertViewHas('lists', function ($lists) use ($list) {
                return $lists->count() === 1
                    && $lists->first()->id === $list->id
                    && (float) $lists->first()->items_total === 25.0
                    && $lists->first()->items_count === 2;
            });
    }
}
