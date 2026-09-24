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

    public function test_index_returns_402_for_free_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/recurring-purchases')
            ->assertStatus(402)
            ->assertJson(['upgrade_required' => true]);
    }

    public function test_index_returns_recurring_data_structure(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/recurring-purchases')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'recurring',
                    'summary',
                    'shopping_lists',
                ],
            ]);
    }

    public function test_add_to_list_returns_401_when_unauthenticated(): void
    {
        $list = ShoppingList::factory()->create();

        $this->postJson('/api/v1/recurring-purchases/add-to-list', [
            'shopping_list_id' => $list->id,
            'description' => 'LEITE INTEGRAL',
            'unit_price' => 5.50,
            'unit' => 'UN',
        ])->assertStatus(401);
    }

    public function test_add_to_list_returns_404_when_list_belongs_to_another_user(): void
    {
        $list = ShoppingList::factory()->create();
        $other = User::factory()->pro()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/recurring-purchases/add-to-list', [
                'shopping_list_id' => $list->id,
                'description' => 'LEITE INTEGRAL',
                'unit_price' => 5.50,
                'unit' => 'UN',
                'issuer_id' => $issuer->id,
            ])->assertStatus(404);
    }

    public function test_add_to_list_adds_item_to_list(): void
    {
        $user = User::factory()->pro()->create();
        $list = ShoppingList::factory()->for($user)->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/recurring-purchases/add-to-list', [
                'shopping_list_id' => $list->id,
                'description' => 'LEITE INTEGRAL',
                'unit_price' => 5.50,
                'unit' => 'UN',
                'issuer_id' => $issuer->id,
            ])->assertStatus(201);

        $this->assertDatabaseHas('shopping_list_items', [
            'shopping_list_id' => $list->id,
            'description' => 'LEITE INTEGRAL',
        ]);
    }

    public function test_add_to_list_without_list_creates_a_new_one(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/recurring-purchases/add-to-list', ['description' => 'LEITE', 'quantity' => 3])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['item_id', 'list_id', 'list_name', 'merged']]);

        $this->assertDatabaseHas('shopping_list_items', ['description' => 'LEITE', 'quantity' => 3, 'issuer_id' => null]);
        $this->assertSame(1, ShoppingList::where('user_id', $user->id)->count());
    }

    public function test_dismiss_and_restore(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/recurring-purchases/dismiss', ['description' => 'LEITE'])->assertOk();
        $this->assertDatabaseHas('recurring_dismissals', ['user_id' => $user->id, 'description' => 'LEITE']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/recurring-purchases/restore', ['description' => 'LEITE'])->assertOk();
        $this->assertDatabaseMissing('recurring_dismissals', ['user_id' => $user->id]);
    }

    public function test_dismiss_requires_description(): void
    {
        $this->actingAs(User::factory()->pro()->create(), 'sanctum')
            ->postJson('/api/v1/recurring-purchases/dismiss', [])
            ->assertStatus(422);
    }

    public function test_replenishment_list_is_422_when_nothing_is_due(): void
    {
        $this->actingAs(User::factory()->pro()->create(), 'sanctum')
            ->postJson('/api/v1/recurring-purchases/replenishment-list')
            ->assertStatus(422);
    }

    public function test_index_rejects_invalid_filters(): void
    {
        $this->actingAs(User::factory()->pro()->create(), 'sanctum')
            ->getJson('/api/v1/recurring-purchases?status=foo')
            ->assertStatus(422);
    }
}
