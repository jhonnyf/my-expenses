<?php

namespace Tests\Feature\Security;

use App\Models\Budget;
use App\Models\Category;
use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Usuário A tenta ler/alterar/apagar dados do usuário B. Tudo que é de B responde 404 (nunca 403,
 * que confirmaria o id; o merge com destino alheio é barrado na validação, 422) e nada de B muda. Única exceção: o preço de B na busca da Lista de Compras.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->create();
        $this->victim = User::factory()->create();
    }

    private function victimInvoice(): Invoice
    {
        $invoice = Invoice::factory()->for($this->victim)->create();
        InvoiceItem::factory()->for($invoice)->create();

        return $invoice;
    }

    // ─── notas e itens ───────────────────────────────────────────────────────

    public function test_web_cannot_view_or_delete_another_users_invoice(): void
    {
        $invoice = $this->victimInvoice();

        $this->actingAs($this->attacker)->get(route('my-purchases.detail', $invoice))->assertNotFound();
        $this->actingAs($this->attacker)->delete(route('my-purchases.destroy', $invoice))->assertNotFound();

        $this->assertModelExists($invoice);
    }

    public function test_api_cannot_view_or_delete_another_users_invoice(): void
    {
        $invoice = $this->victimInvoice();

        $this->actingAs($this->attacker, 'sanctum')->getJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();
        $this->actingAs($this->attacker, 'sanctum')->deleteJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();

        $this->assertModelExists($invoice);
    }

    public function test_lists_only_show_own_invoices(): void
    {
        $this->victimInvoice();
        $own = Invoice::factory()->for($this->attacker)->create();

        $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_categorize_another_users_item(): void
    {
        $item = $this->victimInvoice()->items()->first();
        $category = Category::factory()->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)
            ->postJson(route('categories.assign-item'), ['item_id' => $item->id, 'category_id' => $category->id])
            ->assertNotFound();

        $this->assertNull($item->fresh()->category_id);
    }

    // ─── categorias ──────────────────────────────────────────────────────────

    public function test_cannot_view_update_delete_or_merge_another_users_category(): void
    {
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id]);
        $own = Category::factory()->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->get(route('categories.show', $victimCategory))->assertNotFound();
        $this->actingAs($this->attacker)->patchJson(route('categories.update', $victimCategory), ['name' => 'Hack', 'color' => '#000000'])->assertNotFound();
        $this->actingAs($this->attacker)->deleteJson(route('categories.destroy', $victimCategory))->assertNotFound();
        $this->actingAs($this->attacker)->postJson(route('categories.merge', $own), ['target_id' => $victimCategory->id])->assertUnprocessable();

        $this->assertModelExists($victimCategory);
        $this->assertNotSame('Hack', $victimCategory->fresh()->name);
    }

    public function test_cannot_use_another_users_category_in_budget_or_item(): void
    {
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->postJson(route('budgets.store'), ['category_id' => $victimCategory->id, 'amount' => 100])
            ->assertUnprocessable();

        $item = Invoice::factory()->for($this->attacker)->create()->items()->create(
            InvoiceItem::factory()->make()->getAttributes()
        );
        $this->actingAs($this->attacker)
            ->postJson(route('categories.assign-item'), ['item_id' => $item->id, 'category_id' => $victimCategory->id])
            ->assertUnprocessable();
    }

    // ─── orçamentos ──────────────────────────────────────────────────────────

    public function test_cannot_delete_another_users_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)->deleteJson(route('budgets.destroy', $budget))->assertNotFound();
        $this->actingAs($this->attacker, 'sanctum')->deleteJson("/api/v1/budgets/{$budget->id}")->assertNotFound();

        $this->assertModelExists($budget);
    }

    // ─── listas de compras ───────────────────────────────────────────────────

    public function test_cannot_touch_another_users_shopping_list_or_items(): void
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->victim->id]);
        $item = ShoppingListItem::factory()->create(['shopping_list_id' => $list->id, 'quantity' => 1]);

        $this->actingAs($this->attacker);

        $this->getJson(route('shopping-list.show', $list))->assertNotFound();
        $this->patchJson(route('shopping-list.update', $list), ['name' => 'Hack'])->assertNotFound();
        $this->postJson(route('shopping-list.duplicate', $list))->assertNotFound();
        $this->postJson(route('shopping-list.purchase-all', $list))->assertNotFound();
        $this->postJson(route('shopping-list.items.add', $list), ['description' => 'X', 'quantity' => 1])->assertNotFound();
        $this->patchJson(route('shopping-list.items.update', [$list, $item]), ['quantity' => 9])->assertNotFound();
        $this->deleteJson(route('shopping-list.items.remove', [$list, $item]))->assertNotFound();
        $this->postJson(route('shopping-list.items.toggle-purchased', [$list, $item]))->assertNotFound();
        $this->deleteJson(route('shopping-list.destroy', $list))->assertNotFound();
        $this->getJson("/api/v1/shopping-lists/{$list->id}")->assertNotFound();

        $this->assertModelExists($list);
        $this->assertSame(1, $item->fresh()->quantity);
        $this->assertNull($item->fresh()->purchased_at);
    }

    public function test_item_of_own_list_cannot_be_reached_through_another_list_of_the_same_user(): void
    {
        $ownList = ShoppingList::factory()->create(['user_id' => $this->attacker->id]);
        $victimItem = ShoppingListItem::factory()->create([
            'shopping_list_id' => ShoppingList::factory()->create(['user_id' => $this->victim->id])->id,
        ]);

        $this->actingAs($this->attacker)
            ->deleteJson(route('shopping-list.items.remove', [$ownList, $victimItem]))
            ->assertNotFound();

        $this->assertModelExists($victimItem);
    }

    public function test_cannot_add_recurring_item_to_another_users_list(): void
    {
        $list = ShoppingList::factory()->create(['user_id' => $this->victim->id]);
        $this->attacker->subscription()->update(['plan' => 'pro']);

        $this->actingAs($this->attacker)
            ->postJson(route('recurring-purchases.add-to-list'), [
                'shopping_list_id' => $list->id,
                'description' => 'LEITE',
                'unit' => 'UN',
                'quantity' => 1,
            ])
            ->assertStatus(404);

        $this->assertSame(0, $list->items()->count());
    }

    // ─── favoritos, notificações, exportação ────────────────────────────────

    public function test_cannot_delete_another_users_favorite_product(): void
    {
        $favorite = FavoriteProduct::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)->deleteJson(route('favorite-products.destroy', $favorite))->assertNotFound();
        $this->actingAs($this->attacker, 'sanctum')->deleteJson("/api/v1/favorite-products/{$favorite->id}")->assertNotFound();

        $this->assertModelExists($favorite);
    }

    public function test_cannot_read_or_mark_another_users_notification(): void
    {
        $id = (string) Str::uuid();
        DatabaseNotification::create([
            'id' => $id,
            'type' => 'x',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->victim->id,
            'data' => ['message' => 'segredo'],
        ]);

        $this->actingAs($this->attacker)->postJson(route('notifications.read', $id))->assertNotFound();
        $this->actingAs($this->attacker)->getJson(route('notifications.index'))->assertJsonCount(0, 'notifications');
        $this->assertNull(DatabaseNotification::find($id)->read_at);
    }

    public function test_cannot_download_another_users_data_export(): void
    {
        $file = $this->victim->files()->create([
            'collection' => 'personal-data-export',
            'disk' => 'local',
            'path' => 'exports/'.$this->victim->id.'/x.json',
            'original_name' => 'meus-dados.json',
            'mime_type' => 'application/json',
            'size' => 1,
        ]);

        $url = \URL::signedRoute('account.export.download', $file);

        $this->actingAs($this->attacker)->get($url)->assertStatus(403);
    }

    // ─── emissores ───────────────────────────────────────────────────────────

    public function test_issuer_without_own_invoice_is_not_reachable(): void
    {
        $issuer = Issuer::factory()->create();
        Invoice::factory()->for($this->victim)->create(['issuer_id' => $issuer->id]);

        $this->actingAs($this->attacker)->get(route('issuers.detail', ['id' => $issuer->id]))->assertNotFound();
        $this->actingAs($this->attacker)->postJson(route('issuers.favorite', $issuer->id))->assertNotFound();
        $this->actingAs($this->attacker)->putJson(route('issuers.nickname.update', $issuer->id), ['nickname' => 'x'])->assertNotFound();
        $this->actingAs($this->attacker, 'sanctum')->getJson("/api/v1/issuers/{$issuer->id}")->assertNotFound();

        $this->actingAs($this->attacker)->get(route('issuers.index'))->assertOk()->assertDontSee($issuer->name);
    }

    // ─── única exceção: preço de outro usuário na Lista de Compras ──────────

    public function test_shopping_list_search_shows_others_price_without_identifying_data(): void
    {
        $issuer = Issuer::factory()->create(['city' => 'Goiânia', 'state' => 'GO']);
        $invoice = Invoice::factory()->for($this->victim)->create(['issuer_id' => $issuer->id, 'issued_at' => now()->subDay()]);
        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'ARROZ TIPO UM 5KG',
            'unit_price' => 21.90,
        ]);

        $response = $this->actingAs($this->attacker)
            ->getJson(route('shopping-list.search', ['q' => 'ARROZ', 'city' => 'Goiânia', 'state' => 'GO']))
            ->assertOk();

        $row = $response->json('0');
        $this->assertEquals(21.90, $row['unit_price']);

        $body = $response->getContent();
        foreach ([$this->victim->email, $this->victim->name, $invoice->access_key, (string) $invoice->number] as $secret) {
            $this->assertStringNotContainsString((string) $secret, $body);
        }
        foreach (['user_id', 'invoice_id', 'access_key', 'raw_xml'] as $key) {
            $this->assertArrayNotHasKey($key, $row);
        }
    }
}
