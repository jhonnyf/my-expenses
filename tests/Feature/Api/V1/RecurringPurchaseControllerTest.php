<?php

namespace Tests\Feature\Api\V1;

use App\Models\Issuer;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/recurring-purchases')->assertStatus(401);
    }

    public function test_index_returns_recurring_data_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/recurring-purchases')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'recurring',
                    'best_issuers',
                    'shopping_lists',
                ],
            ]);
    }

    public function test_add_to_list_returns_401_when_unauthenticated(): void
    {
        $list = ShoppingList::factory()->create();

        $this->postJson('/api/v1/recurring-purchases/add-to-list', [
            'shopping_list_id' => $list->id,
            'description'      => 'LEITE INTEGRAL',
            'unit_price'       => 5.50,
            'unit'             => 'UN',
        ])->assertStatus(401);
    }

    public function test_add_to_list_returns_403_when_list_belongs_to_another_user(): void
    {
        $list   = ShoppingList::factory()->create();
        $other  = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/recurring-purchases/add-to-list', [
                'shopping_list_id' => $list->id,
                'description'      => 'LEITE INTEGRAL',
                'unit_price'       => 5.50,
                'unit'             => 'UN',
                'issuer_id'        => $issuer->id,
            ])->assertStatus(403);
    }

    public function test_add_to_list_adds_item_to_list(): void
    {
        $user   = User::factory()->create();
        $list   = ShoppingList::factory()->for($user)->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/recurring-purchases/add-to-list', [
                'shopping_list_id' => $list->id,
                'description'      => 'LEITE INTEGRAL',
                'unit_price'       => 5.50,
                'unit'             => 'UN',
                'issuer_id'        => $issuer->id,
            ])->assertStatus(201);

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'description'      => 'LEITE INTEGRAL',
        ]);
    }
}
