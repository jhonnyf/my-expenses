<?php

namespace Tests\Feature\Api\V1;

use App\Models\Issuer;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/shopping-lists')->assertStatus(401);
    }

    public function test_index_returns_lists_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        ShoppingList::factory()->count(2)->for($user)->create();
        ShoppingList::factory()->create(); // de outro usuário

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping-lists')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_shopping_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/shopping-lists', ['name' => 'Mercado'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Mercado');

        $this->assertDatabaseHas('shopping_lists', [
            'user_id' => $user->id,
            'name'    => 'Mercado',
        ]);
    }

    public function test_show_returns_401_when_unauthenticated(): void
    {
        $list = ShoppingList::factory()->create();

        $this->getJson("/api/v1/shopping-lists/{$list->id}")->assertStatus(401);
    }

    public function test_show_returns_403_when_list_belongs_to_another_user(): void
    {
        $list  = ShoppingList::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/shopping-lists/{$list->id}")
            ->assertStatus(403);
    }

    public function test_show_returns_list_for_owner(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shopping-lists/{$list->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $list->id);
    }

    public function test_destroy_returns_403_when_list_belongs_to_another_user(): void
    {
        $list  = ShoppingList::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/shopping-lists/{$list->id}")
            ->assertStatus(403);
    }

    public function test_destroy_deletes_own_list(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping-lists/{$list->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    public function test_update_returns_403_for_other_users_list(): void
    {
        $list  = ShoppingList::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/v1/shopping-lists/{$list->id}", ['name' => 'Nova'])
            ->assertStatus(403);
    }

    public function test_update_renames_list(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create(['name' => 'Antiga']);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/shopping-lists/{$list->id}", ['name' => 'Nova Lista'])
            ->assertStatus(200);

        $this->assertDatabaseHas('shopping_lists', ['id' => $list->id, 'name' => 'Nova Lista']);
    }

    public function test_search_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/shopping-lists/search?q=leite')->assertStatus(401);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping-lists/search?q=a')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_add_item_returns_403_for_other_users_list(): void
    {
        $list   = ShoppingList::factory()->create();
        $other  = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/shopping-lists/{$list->id}/items", [
                'description' => 'Leite',
                'unit'        => 'UN',
                'unit_price'  => 5.00,
                'quantity'    => 1,
                'issuer_id'   => $issuer->id,
            ])->assertStatus(403);
    }

    public function test_add_item_to_list(): void
    {
        $user   = User::factory()->create();
        $list   = ShoppingList::factory()->for($user)->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping-lists/{$list->id}/items", [
                'description' => 'Leite Integral',
                'unit'        => 'UN',
                'unit_price'  => 5.50,
                'quantity'    => 2,
                'issuer_id'   => $issuer->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.description', 'Leite Integral');

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'description'      => 'Leite Integral',
        ]);
    }

    public function test_update_item_quantity(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create();
        $item = ShoppingListItem::factory()->for($list)->create(['quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/shopping-lists/{$list->id}/items/{$item->id}", ['quantity' => 5])
            ->assertStatus(200);

        $this->assertDatabaseHas('shopping_list_items', ['id' => $item->id, 'quantity' => 5]);
    }

    public function test_update_item_returns_404_when_item_not_in_list(): void
    {
        $user      = User::factory()->create();
        $list      = ShoppingList::factory()->for($user)->create();
        $otherList = ShoppingList::factory()->for($user)->create();
        $item      = ShoppingListItem::factory()->for($otherList)->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/shopping-lists/{$list->id}/items/{$item->id}", ['quantity' => 5])
            ->assertStatus(404);
    }

    public function test_remove_item_from_list(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create();
        $item = ShoppingListItem::factory()->for($list)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping-lists/{$list->id}/items/{$item->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('shopping_list_items', ['id' => $item->id]);
    }

    public function test_toggle_purchased_marks_item(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create();
        $item = ShoppingListItem::factory()->for($list)->create(['purchased_at' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping-lists/{$list->id}/items/{$item->id}/toggle-purchased")
            ->assertStatus(200);

        $this->assertNotNull($response->json('data.purchased_at'));
    }

    public function test_toggle_purchased_unmarks_item(): void
    {
        $user = User::factory()->create();
        $list = ShoppingList::factory()->for($user)->create();
        $item = ShoppingListItem::factory()->for($list)->create(['purchased_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping-lists/{$list->id}/items/{$item->id}/toggle-purchased")
            ->assertStatus(200);

        $this->assertNull($response->json('data.purchased_at'));
    }
}
