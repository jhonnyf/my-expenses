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

/**
 * Lista de Compras: busca (preço mais recente por mercado, do menor ao maior), validação, isolamento,
 * soma de itens repetidos, totais, duplicar, marcar tudo, atualizar preços e economia.
 */
class ShoppingListManagementTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(string $description, float $price, ?Issuer $issuer = null, int $daysAgo = 1, ?User $by = null): InvoiceItem
    {
        $invoice = Invoice::factory()->create([
            'user_id' => ($by ?? User::factory()->create())->id,
            'issuer_id' => ($issuer ?? Issuer::factory()->create())->id,
            'issued_at' => now()->subDays($daysAgo),
        ]);

        return InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => $description, 'unit_price' => $price, 'total_price' => $price]);
    }

    private function search(User $user, string $query): array
    {
        return $this->actingAs($user)->getJson('/shopping-list/search?q='.urlencode($query))->assertOk()->json();
    }

    private function listWith(User $user, array $items = []): ShoppingList
    {
        $list = ShoppingList::create(['user_id' => $user->id, 'name' => 'Feira']);

        foreach ($items as $item) {
            $list->items()->create($item);
        }

        return $list;
    }

    // ─── Busca: preço mais recente por produto e mercado, do menor ao maior ───────────────────

    public function test_search_keeps_only_the_latest_purchase_of_each_product_per_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchase('LEITE INTEGRAL 1L', 10, $issuer, 30);
        $this->purchase('LEITE INTEGRAL 1L', 8, $issuer, 20);
        $this->purchase('LEITE INTEGRAL 1L', 12, $issuer, 2);

        $results = $this->search($user, 'LEITE');

        $this->assertCount(1, $results);
        $this->assertEquals(12, $results[0]['unit_price']); // o preço de hoje, não o mais barato do histórico
    }

    public function test_search_orders_current_prices_from_lowest_to_highest_and_flags_old_ones(): void
    {
        $user = User::factory()->create();
        $a = Issuer::factory()->create(['name' => 'Mercado A']);
        $b = Issuer::factory()->create(['name' => 'Mercado B']);
        $c = Issuer::factory()->create(['name' => 'Mercado C']);
        $this->purchase('ARROZ TIPO 1 5KG', 9.50, $a, 5);
        $this->purchase('ARROZ TIPO 1 5KG', 7.00, $b, 10);
        $this->purchase('ARROZ TIPO 1 5KG', 3.00, $c, 200); // mais barato, mas de 200 dias atrás

        $results = $this->search($user, 'ARROZ');

        $this->assertSame(['Mercado B', 'Mercado A', 'Mercado C'], array_column($results, 'issuer_name'));
        $this->assertSame([false, false, true], array_column($results, 'is_stale'));
    }

    public function test_repeated_purchases_do_not_crowd_out_other_products_and_issuers(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        foreach (range(1, 30) as $day) {
            $this->purchase('CAFE PILAO 500G', 20 + $day, $issuer, $day);
        }
        $this->purchase('CAFE 3 CORACOES 500G', 18, $issuer, 3);
        $this->purchase('CAFE PILAO 500G', 25, Issuer::factory()->create(), 4);

        $results = $this->search($user, 'CAFE');

        $this->assertCount(3, $results); // Pilão em 2 mercados + 3 Corações
        $this->assertSame(['CAFE 3 CORACOES 500G', 'CAFE PILAO 500G', 'CAFE PILAO 500G'], array_column($results, 'description'));
    }

    public function test_search_merges_raw_names_that_share_the_users_alias_per_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchase('COCA COLA 350ML LT', 5, $issuer, 10);
        $this->purchase('REFRIG COCA-COLA 350', 4, $issuer, 2);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA COLA 350ML LT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA-COLA 350', 'canonical_name' => 'Coca-Cola 350ml']);

        $results = $this->search($user, 'coca');

        $this->assertCount(1, $results);
        $this->assertEquals(4, $results[0]['unit_price']);
        $this->assertSame('Coca-Cola 350ml', $results[0]['description']);
    }

    // ─── Validação ───────────────────────────────────────────────────────────────────────────

    public function test_store_validates_the_name_and_answers_json_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shopping-list', ['name' => str_repeat('a', 256)])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($user)->postJson('/shopping-list', ['name' => ['x']])->assertStatus(422);
        $this->actingAs($user)->postJson('/shopping-list', [])->assertOk()->assertJsonPath('name', 'Lista de compras '.now()->format('d/m/Y'));
    }

    public function test_add_item_validates_input_with_json_errors(): void
    {
        $user = User::factory()->create();
        $list = $this->listWith($user);
        $post = fn (array $data) => $this->actingAs($user)->postJson("/shopping-list/{$list->id}/items", $data + ['description' => 'PAO', 'quantity' => 1]);

        $post(['description' => str_repeat('x', 256)])->assertStatus(422)->assertJsonValidationErrors('description');
        $post(['unit' => str_repeat('x', 21)])->assertStatus(422)->assertJsonValidationErrors('unit');
        $post(['unit_price' => -1])->assertStatus(422)->assertJsonValidationErrors('unit_price');
        $post(['unit_price' => 10000000])->assertStatus(422)->assertJsonValidationErrors('unit_price');
        $post(['quantity' => 0])->assertStatus(422)->assertJsonValidationErrors('quantity');
        $post(['quantity' => 10000])->assertStatus(422)->assertJsonValidationErrors('quantity');
        $post(['issuer_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('issuer_id');
        $this->actingAs($user)->patchJson("/shopping-list/{$list->id}", ['name' => ''])->assertStatus(422);

        $this->assertDatabaseCount('shopping_list_items', 0);
    }

    // ─── Isolamento ──────────────────────────────────────────────────────────────────────────

    public function test_other_users_list_answers_404_everywhere(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $list = $this->listWith($owner, [['description' => 'PAO', 'quantity' => 1]]);
        $item = $list->items->first() ?? $list->items()->first();

        foreach ([
            ['get', "/shopping-list/{$list->id}"],
            ['patch', "/shopping-list/{$list->id}"],
            ['delete', "/shopping-list/{$list->id}"],
            ['post', "/shopping-list/{$list->id}/items"],
            ['post', "/shopping-list/{$list->id}/duplicate"],
            ['post', "/shopping-list/{$list->id}/purchase-all"],
            ['post', "/shopping-list/{$list->id}/refresh-prices"],
            ['get', "/shopping-list/{$list->id}/savings"],
            ['post', "/shopping-list/{$list->id}/items/{$item->id}/toggle-purchased"],
        ] as [$method, $url]) {
            $this->actingAs($intruder)->json($method, $url, ['name' => 'x', 'description' => 'x', 'quantity' => 1])->assertNotFound();
        }

        $this->assertModelExists($list);
    }

    // ─── Itens repetidos ─────────────────────────────────────────────────────────────────────

    public function test_adding_the_same_product_from_the_same_issuer_sums_the_quantity(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $list = $this->listWith($user);
        $add = fn (array $extra = []) => $this->actingAs($user)->postJson("/shopping-list/{$list->id}/items", ['description' => 'LEITE', 'quantity' => 2, 'unit_price' => 5, 'issuer_id' => $issuer->id, ...$extra]);

        $first = $add()->assertOk()->assertJsonPath('merged', false);
        $second = $add(['unit_price' => 6])->assertOk()->assertJsonPath('merged', true)->assertJsonPath('quantity', 4);

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('shopping_list_items', 1);
        $this->assertEquals(6, $list->items()->first()->unit_price);
    }

    public function test_repeated_generic_items_merge_but_purchased_or_other_issuer_items_do_not(): void
    {
        $user = User::factory()->create();
        $list = $this->listWith($user);
        $add = fn (array $data) => $this->actingAs($user)->postJson("/shopping-list/{$list->id}/items", ['description' => 'PAPEL', 'quantity' => 1, ...$data]);

        $add([])->assertOk();
        $add([])->assertJsonPath('merged', true);
        $this->assertDatabaseCount('shopping_list_items', 1);

        $list->items()->first()->update(['purchased_at' => now()]);
        $add([])->assertJsonPath('merged', false); // o comprado não recebe soma
        $add(['issuer_id' => Issuer::factory()->create()->id, 'unit_price' => 3])->assertJsonPath('merged', false);
        $this->assertDatabaseCount('shopping_list_items', 3);
    }

    public function test_api_add_item_answers_201_for_a_new_line_and_200_when_merged(): void
    {
        $user = User::factory()->create();
        $list = $this->listWith($user);
        $url = "/api/v1/shopping-lists/{$list->id}/items";

        $this->actingAs($user, 'sanctum')->postJson($url, ['description' => 'OVO', 'quantity' => 1])->assertStatus(201)->assertJsonPath('data.merged', false);
        $this->actingAs($user, 'sanctum')->postJson($url, ['description' => 'OVO', 'quantity' => 1])->assertStatus(200)->assertJsonPath('data.merged', true)->assertJsonPath('data.quantity', 2);
    }

    public function test_recurring_purchase_add_to_list_sums_repeated_items_and_validates_input(): void
    {
        $user = User::factory()->pro()->create();
        $issuer = Issuer::factory()->create();
        $list = $this->listWith($user);
        $payload = ['shopping_list_id' => $list->id, 'description' => 'LEITE', 'unit_price' => 5, 'issuer_id' => $issuer->id, 'unit' => 'UN'];
        $list->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();

        $this->actingAs($user)->postJson('/recurring-purchases/add-to-list', $payload)->assertOk();
        $this->actingAs($user)->postJson('/recurring-purchases/add-to-list', $payload)->assertOk();

        $this->assertDatabaseCount('shopping_list_items', 1);
        $this->assertSame(2, $list->items()->first()->quantity);
        $this->assertTrue($list->fresh()->updated_at->isToday());

        $this->actingAs($user)->postJson('/recurring-purchases/add-to-list', [...$payload, 'description' => str_repeat('x', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('description');
    }

    // ─── Totais nas listas salvas ────────────────────────────────────────────────────────────

    public function test_saved_lists_expose_total_and_purchased_count(): void
    {
        $user = User::factory()->create();
        $this->listWith($user, [
            ['description' => 'A', 'quantity' => 2, 'unit_price' => 5, 'purchased_at' => now()],
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 10],
            ['description' => 'C', 'quantity' => 3, 'unit_price' => null],
        ]);
        $this->listWith(User::factory()->create(), [['description' => 'Z', 'quantity' => 9, 'unit_price' => 100]]);

        $this->actingAs($user)->get('/shopping-list')->assertOk()->assertViewHas('lists', function ($lists) {
            $list = $lists->first();

            return $lists->count() === 1 && $list->items_count === 3 && $list->purchased_count === 1 && (float) $list->items_total === 20.0;
        });

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/shopping-lists')
            ->assertJsonPath('data.0.items_count', 3)
            ->assertJsonPath('data.0.purchased_count', 1)
            ->assertJsonPath('data.0.items_total', 20);
    }

    // ─── Duplicar e marcar tudo ──────────────────────────────────────────────────────────────

    public function test_duplicate_copies_items_as_pending_and_keeps_the_original(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $list = $this->listWith($user, [
            ['description' => 'A', 'quantity' => 2, 'unit_price' => 5, 'issuer_id' => $issuer->id, 'purchased_at' => now()],
            ['description' => 'B', 'quantity' => 1],
        ]);

        $copy = $this->actingAs($user)->postJson("/shopping-list/{$list->id}/duplicate")->assertOk()->assertJsonPath('name', 'Feira (cópia)');

        $copied = ShoppingListItem::where('shopping_list_id', $copy->json('id'))->orderBy('id')->get();
        $this->assertCount(2, $copied);
        $this->assertNull($copied[0]->purchased_at);
        $this->assertSame($issuer->id, $copied[0]->issuer_id);
        $this->assertSame(2, $copied[0]->quantity);
        $this->assertNotNull($list->items()->orderBy('id')->first()->purchased_at);
        $this->assertSame($user->id, ShoppingList::find($copy->json('id'))->user_id);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/shopping-lists/{$list->id}/duplicate")->assertStatus(201)->assertJsonPath('data.items_count', 2);
    }

    public function test_purchase_all_marks_only_pending_items(): void
    {
        $user = User::factory()->create();
        $earlier = now()->subDay()->startOfSecond();
        $list = $this->listWith($user, [
            ['description' => 'A', 'quantity' => 1, 'purchased_at' => $earlier],
            ['description' => 'B', 'quantity' => 1],
            ['description' => 'C', 'quantity' => 1],
        ]);

        $this->actingAs($user)->postJson("/shopping-list/{$list->id}/purchase-all")->assertOk()->assertJsonPath('purchased', 2);

        $this->assertSame(0, $list->items()->whereNull('purchased_at')->count());
        $this->assertTrue($list->items()->where('description', 'A')->first()->purchased_at->equalTo($earlier));
    }

    // ─── Atualizar preços ────────────────────────────────────────────────────────────────────

    public function test_refresh_prices_uses_the_latest_purchase_at_the_same_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $other = Issuer::factory()->create();
        $this->purchase('LEITE INTEGRAL 1L', 4.00, $issuer, 40);
        $this->purchase('LEITE INTEGRAL 1L', 5.50, $issuer, 2);         // de outro usuário: vale (comunidade)
        $this->purchase('LEITE INTEGRAL 1L', 1.00, $other, 1);          // outro mercado: não conta
        $list = $this->listWith($user, [
            ['description' => 'LEITE INTEGRAL 1L', 'quantity' => 2, 'unit_price' => 4.00, 'issuer_id' => $issuer->id],
            ['description' => 'LEITE INTEGRAL 1L', 'quantity' => 1, 'unit_price' => 9.99, 'issuer_id' => $issuer->id, 'purchased_at' => now()], // comprado: fica
            ['description' => 'PAPEL', 'quantity' => 1, 'unit_price' => 7.00],                                                                 // sem mercado: fica
        ]);

        $response = $this->actingAs($user)->postJson("/shopping-list/{$list->id}/refresh-prices")->assertOk();

        $response->assertJsonPath('updated', 1);
        $this->assertEquals(5.5, $response->json('changes.0.new'));
        $this->assertEquals(4.0, $response->json('changes.0.old'));
        $this->assertEquals(5.5 * 2 + 9.99 + 7.00, $response->json('total'));
        $this->assertEquals(9.99, $list->items()->where('description', 'LEITE INTEGRAL 1L')->whereNotNull('purchased_at')->first()->unit_price);

        $this->actingAs($user)->postJson("/shopping-list/{$list->id}/refresh-prices")->assertJsonPath('updated', 0);
    }

    public function test_refresh_prices_follows_the_users_product_alias(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchase('COCA COLA 350ML LT', 6.00, $issuer, 1);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA COLA 350ML LT', 'canonical_name' => 'Coca-Cola 350ml']);
        $list = $this->listWith($user, [['description' => 'Coca-Cola 350ml', 'quantity' => 1, 'unit_price' => 4.00, 'issuer_id' => $issuer->id]]);

        $this->actingAs($user)->postJson("/shopping-list/{$list->id}/refresh-prices")->assertOk()->assertJsonPath('updated', 1);
        $this->assertEquals(6.0, $list->items()->first()->unit_price);
    }

    // ─── Economia ────────────────────────────────────────────────────────────────────────────

    public function test_savings_suggests_the_cheapest_current_price_at_another_issuer(): void
    {
        $user = User::factory()->create();
        $current = Issuer::factory()->create(['name' => 'Mercado Caro']);
        $cheap = Issuer::factory()->create(['name' => 'Mercado Barato']);
        $cheaper = Issuer::factory()->create(['name' => 'Mercado Antigo']);
        $this->purchase('ARROZ TIPO 1 5KG', 12.00, $current, 3);
        $this->purchase('ARROZ TIPO 1 5KG', 9.00, $cheap, 4);
        $this->purchase('ARROZ TIPO 1 5KG', 5.00, $cheaper, 300);   // barato, mas preço antigo: ignorado
        $list = $this->listWith($user, [
            ['description' => 'ARROZ TIPO 1 5KG', 'quantity' => 3, 'unit_price' => 12.00, 'issuer_id' => $current->id],
            ['description' => 'LEITE', 'quantity' => 1, 'unit_price' => 5.00, 'issuer_id' => $current->id],   // sem alternativa
            ['description' => 'PAPEL', 'quantity' => 1],                                                        // sem mercado/preço
        ]);

        $response = $this->actingAs($user)->getJson("/shopping-list/{$list->id}/savings")->assertOk();

        $this->assertCount(1, $response->json('suggestions'));
        $suggestion = $response->json('suggestions.0');
        $this->assertSame('Mercado Barato', $suggestion['issuer_name']);
        $this->assertEquals(9.0, $suggestion['unit_price']);
        $this->assertEquals(9.0, $suggestion['saving']); // (12 − 9) × 3
        $this->assertEquals(9.0, $response->json('total_saving'));
    }

    public function test_savings_is_empty_when_the_current_issuer_is_already_the_cheapest(): void
    {
        $user = User::factory()->create();
        $current = Issuer::factory()->create();
        $this->purchase('FEIJAO 1KG', 6.00, $current, 3);
        $this->purchase('FEIJAO 1KG', 7.00, Issuer::factory()->create(), 3);
        $list = $this->listWith($user, [['description' => 'FEIJAO 1KG', 'quantity' => 1, 'unit_price' => 6.00, 'issuer_id' => $current->id]]);

        $this->actingAs($user)->getJson("/shopping-list/{$list->id}/savings")->assertOk()->assertJsonPath('total_saving', 0)->assertJsonCount(0, 'suggestions');
    }

    public function test_api_price_endpoints(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchase('SABAO EM PO 1KG', 8.00, $issuer, 1);
        $list = $this->listWith($user, [['description' => 'SABAO EM PO 1KG', 'quantity' => 1, 'unit_price' => 5.00, 'issuer_id' => $issuer->id]]);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/shopping-lists/{$list->id}/refresh-prices")->assertOk()->assertJsonPath('data.updated', 1);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/shopping-lists/{$list->id}/savings")->assertOk()->assertJsonStructure(['data' => ['suggestions', 'total_saving']]);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/shopping-lists/{$list->id}/purchase-all")->assertOk()->assertJsonPath('data.purchased', 1);
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/v1/shopping-lists/{$list->id}/savings")->assertNotFound();
    }
}
