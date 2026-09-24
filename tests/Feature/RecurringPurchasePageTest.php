<?php

namespace Tests\Feature;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringPurchasePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_for_pro_user(): void
    {
        $this->actingAs(User::factory()->pro()->create())
            ->get(route('recurring-purchases.index', ['status' => 'all', 'sort' => 'name']))
            ->assertOk()
            ->assertSee('Compras Recorrentes');
    }

    public function test_page_requires_pro_plan(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('recurring-purchases.index'))
            ->assertStatus(402);
    }

    public function test_add_to_list_from_another_user_is_not_found(): void
    {
        $list = ShoppingList::factory()->for(User::factory()->create())->create();

        $this->actingAs(User::factory()->pro()->create())
            ->postJson(route('recurring-purchases.add-to-list'), ['shopping_list_id' => $list->id, 'description' => 'LEITE'])
            ->assertStatus(404);
    }

    public function test_dismiss_validates_as_json(): void
    {
        $this->actingAs(User::factory()->pro()->create())
            ->postJson(route('recurring-purchases.dismiss'), [])
            ->assertStatus(422);
    }
}
