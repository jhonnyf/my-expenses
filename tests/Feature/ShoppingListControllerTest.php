<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
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

    public function test_search_finds_item_by_canonical_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);

        $response = $this->actingAs($user)->getJson('/shopping-list/search?q=Coca-Cola');

        $response->assertStatus(200);
        $this->assertEquals('Coca-Cola 350ml', $response->json('0.description'));
    }

    public function test_search_finds_item_from_other_users_invoice(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($other)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ TIPO 1 5KG']);

        $response = $this->actingAs($user)->getJson('/shopping-list/search?q=ARROZ');

        $response->assertStatus(200);
        $this->assertEquals('ARROZ TIPO 1 5KG', $response->json('0.description'));
        $this->assertEquals(0, $response->json('0.is_own'));
    }

    public function test_search_marks_own_items_as_own(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'FEIJAO CARIOCA 1KG']);

        $response = $this->actingAs($user)->getJson('/shopping-list/search?q=FEIJAO');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('0.is_own'));
    }

    public function test_show_includes_issuer_address_for_directions(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create([
            'street' => 'Avenida Paulista',
            'street_number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01310100',
        ]);
        $list = ShoppingList::factory()->for($user)->create();
        ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'issuer_id' => $issuer->id]);

        $response = $this->actingAs($user)->getJson("/shopping-list/{$list->id}");

        $response->assertStatus(200);
        $this->assertEquals('Avenida Paulista', $response->json('items.0.issuer.street'));
        $this->assertEquals('São Paulo', $response->json('items.0.issuer.city'));
        $this->assertEquals('01310100', $response->json('items.0.issuer.zip_code'));
    }
}
