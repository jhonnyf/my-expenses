<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShoppingList;
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
}
