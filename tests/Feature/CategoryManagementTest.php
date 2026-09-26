<?php

namespace Tests\Feature;

use App\Jobs\AiCategorizeItemsJob;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\ItemCategoryRule;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Segurança e regras da página de Categorias: isolamento por usuário, validação, comparação de
 * períodos, itens sem categoria, detalhe, prévia de palavras-chave, reverter e mesclar.
 */
class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = 'start_date=2026-06-11&end_date=2026-06-20';

    private function itemOf(User $user, array $item = [], array $invoice = []): InvoiceItem
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => Issuer::factory()->create()->id,
            'issued_at' => '2026-06-15 10:00:00',
            ...$invoice,
        ]);

        return InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => null, ...$item]);
    }

    private function categoryOf(User $user, array $attributes = []): Category
    {
        return Category::factory()->create(['user_id' => $user->id, ...$attributes]);
    }

    // ─── Segurança: categoria e item de outro usuário ─────────────────────────────────────

    public function test_assign_item_rejects_category_of_another_user_without_touching_the_item(): void
    {
        $user = User::factory()->create();
        $foreign = $this->categoryOf(User::factory()->create(), ['name' => 'Segredo']);
        $item = $this->itemOf($user);

        $this->actingAs($user)
            ->postJson('/categories/assign-item', ['item_id' => $item->id, 'category_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');

        $this->assertNull($item->fresh()->category_id);
        $this->assertDatabaseCount('item_category_rules', 0);
    }

    public function test_assign_item_accepts_own_and_system_categories(): void
    {
        $user = User::factory()->create();
        $own = $this->categoryOf($user);
        $system = Category::factory()->create(['user_id' => null]);
        $item = $this->itemOf($user);

        $this->actingAs($user)->postJson('/categories/assign-item', ['item_id' => $item->id, 'category_id' => $own->id])->assertOk();
        $this->assertSame($own->id, $item->fresh()->category_id);

        $this->actingAs($user)->postJson('/categories/assign-item', ['item_id' => $item->id, 'category_id' => $system->id])->assertOk();
        $this->assertSame($system->id, $item->fresh()->category_id);
    }

    public function test_api_assign_item_rejects_category_of_another_user(): void
    {
        $user = User::factory()->create();
        $foreign = $this->categoryOf(User::factory()->create());
        $item = $this->itemOf($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories/assign-item', ['item_id' => $item->id, 'category_id' => $foreign->id])
            ->assertStatus(422);

        $this->assertNull($item->fresh()->category_id);
    }

    // ─── Validação ─────────────────────────────────────────────────────────────────────────

    public function test_store_rejects_duplicate_name_for_the_same_user_only(): void
    {
        $user = User::factory()->create();
        $this->categoryOf($user, ['name' => 'Mercado']);
        $this->categoryOf(User::factory()->create(), ['name' => 'Feira']);

        $this->actingAs($user)->postJson('/categories', ['name' => 'Mercado'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($user)->postJson('/categories', ['name' => 'Feira'])->assertOk();
    }

    public function test_update_allows_keeping_its_own_name_and_keeps_color_when_omitted(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryOf($user, ['name' => 'Mercado', 'color' => '#112233']);

        $this->actingAs($user)->patchJson("/categories/{$category->id}", ['name' => 'Mercado', 'keywords' => 'ARROZ'])->assertOk();

        $this->assertSame('#112233', $category->fresh()->color);
    }

    public function test_store_uses_default_color_and_rejects_invalid_color(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/categories', ['name' => 'A'])->assertOk()->assertJsonPath('color', '#94A3B8');
        $this->actingAs($user)->postJson('/categories', ['name' => 'B', 'color' => 'red;'])->assertStatus(422)->assertJsonValidationErrors('color');
    }

    public function test_keywords_are_cleaned_and_limited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/categories', ['name' => 'A', 'keywords' => 'arroz, ,ARROZ,feijao,'])
            ->assertOk()->assertJsonPath('keywords', ['arroz', 'feijao']);

        $tooMany = implode(',', array_map(fn ($i) => "k{$i}", range(1, 51)));
        $this->actingAs($user)->postJson('/categories', ['name' => 'B', 'keywords' => $tooMany])->assertStatus(422)->assertJsonValidationErrors('keywords');
        $this->actingAs($user)->postJson('/categories', ['name' => 'C', 'keywords' => str_repeat('x', 41)])->assertStatus(422)->assertJsonValidationErrors('keywords');
    }

    // ─── Lista: período anterior, ordem e sem categoria ─────────────────────────────────────

    public function test_index_compares_each_category_with_the_previous_period(): void
    {
        $user = User::factory()->create();
        $growing = $this->categoryOf($user, ['name' => 'Cresceu']);
        $new = $this->categoryOf($user, ['name' => 'Nova']);
        // período atual 11–20/06; anterior 01–10/06
        $this->itemOf($user, ['category_id' => $growing->id, 'total_price' => 150]);
        $this->itemOf($user, ['category_id' => $growing->id, 'total_price' => 100], ['issued_at' => '2026-06-05 10:00:00']);
        $this->itemOf($user, ['category_id' => $new->id, 'total_price' => 40]);

        $this->actingAs($user)->get('/categories?'.self::PERIOD)->assertViewHas('categories', function ($categories) use ($growing, $new) {
            return $categories->firstWhere('id', $growing->id)->delta_pct === 50.0
                && $categories->firstWhere('id', $new->id)->delta_pct === null;
        });
    }

    public function test_index_has_no_delta_for_all_time_and_orders_ties_by_name(): void
    {
        $user = User::factory()->create();
        $this->categoryOf($user, ['name' => 'Zebra']);
        $this->categoryOf($user, ['name' => 'Abelha']);
        $this->itemOf($user, ['category_id' => $this->categoryOf($user, ['name' => 'Gasta'])->id, 'total_price' => 10]);

        $this->actingAs($user)->get('/categories?start_date=2000-01-01&end_date=2026-06-30')
            ->assertViewHas('categories', fn ($categories) => $categories->pluck('delta_pct')->filter(fn ($d) => $d !== null)->isEmpty()
                && $categories->pluck('name')->all() === ['Gasta', 'Abelha', 'Zebra']);
    }

    public function test_index_reports_uncategorized_count_and_spending(): void
    {
        $user = User::factory()->create();
        $this->itemOf($user, ['total_price' => 30]);
        $this->itemOf($user, ['total_price' => 20]);
        $this->itemOf($user, ['category_id' => $this->categoryOf($user)->id, 'total_price' => 999]);
        $this->itemOf(User::factory()->create(), ['total_price' => 777]);

        $this->actingAs($user)->get('/categories?'.self::PERIOD)
            ->assertViewHas('uncategorized', ['count' => 2, 'total' => 50.0])
            ->assertViewHas('uncategorizedCount', 2);
    }

    public function test_index_exposes_lifetime_item_count_and_budget_per_category(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryOf($user);
        $this->itemOf($user, ['category_id' => $category->id], ['issued_at' => '2024-01-10 10:00:00']);
        $this->itemOf($user, ['category_id' => $category->id]);
        Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id, 'amount' => 300]);

        $this->actingAs($user)->get('/categories?'.self::PERIOD)
            ->assertViewHas('categories', fn ($categories) => $categories->firstWhere('id', $category->id)->total_items_count === 2)
            ->assertViewHas('budgetsByCategory', fn ($budgets) => $budgets->has($category->id));
    }

    // ─── Itens sem categoria ────────────────────────────────────────────────────────────────

    public function test_uncategorized_page_lists_only_own_items_with_canonical_name_and_issuer(): void
    {
        $user = User::factory()->create();
        $item = $this->itemOf($user, ['description' => 'CAFE TORRADO 500G']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'CAFE TORRADO 500G', 'canonical_name' => 'Café Torrado']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $item->invoice->issuer_id, 'nickname' => 'Mercadinho']);
        $this->itemOf(User::factory()->create());
        $this->itemOf($user, ['category_id' => $this->categoryOf($user)->id]);

        $this->actingAs($user)->get('/categories/uncategorized?'.self::PERIOD)
            ->assertOk()
            ->assertViewHas('items', function ($items) use ($item) {
                $row = $items->getCollection()->first();

                return $items->total() === 1 && $row->id === $item->id
                    && $row->canonical_name === 'Café Torrado' && $row->issuer_name === 'Mercadinho';
            });
    }

    public function test_api_uncategorized_returns_items_with_summary(): void
    {
        $user = User::factory()->create();
        $this->itemOf($user, ['total_price' => 12.5]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/categories/uncategorized?'.self::PERIOD)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'invoice_id', 'description', 'canonical_name', 'total_price', 'issued_at', 'issuer_name']], 'meta_summary' => ['count', 'total']])
            ->assertJsonPath('meta_summary.total', 12.5);
    }

    // ─── Detalhe da categoria ───────────────────────────────────────────────────────────────

    public function test_show_page_gives_insights_of_the_users_own_purchases_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = $this->categoryOf($user);
        $this->itemOf($user, ['category_id' => $category->id, 'description' => 'LEITE', 'total_price' => 30]);
        $this->itemOf($user, ['category_id' => $category->id, 'description' => 'LEITE', 'total_price' => 20]);
        $this->itemOf($other, ['category_id' => $category->id, 'description' => 'SEGREDO', 'total_price' => 999]);

        $this->actingAs($user)->get("/categories/{$category->id}?".self::PERIOD)
            ->assertOk()
            ->assertViewHas('detail', function ($detail) {
                return $detail['total'] === 50.0 && $detail['items'] === 2
                    && count($detail['monthly']) === 12
                    && array_column($detail['top_products'], 'name') === ['LEITE']
                    && count($detail['top_issuers']) === 2;
            });
    }

    public function test_show_page_is_404_for_other_users_category_and_open_for_system_ones(): void
    {
        $user = User::factory()->create();
        $foreign = $this->categoryOf(User::factory()->create());
        $system = Category::factory()->create(['user_id' => null]);

        $this->actingAs($user)->get("/categories/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->get("/categories/{$system->id}")->assertOk();
    }

    public function test_api_show_returns_insights(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryOf($user);
        $this->itemOf($user, ['category_id' => $category->id, 'total_price' => 40]);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/categories/{$category->id}?".self::PERIOD)
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.insights.total', 40)
            ->assertJsonCount(12, 'data.insights.monthly')
            ->assertJsonStructure(['data' => ['insights' => ['items', 'delta_pct', 'top_products', 'top_issuers']]]);
    }

    public function test_report_index_accepts_filters_from_the_url(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryOf($user);

        $this->actingAs($user)->get('/reports?category_id='.$category->id.'&start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->assertViewHas('filters', fn ($filters) => (int) $filters['category_id'] === $category->id && $filters['start_date'] === '2026-06-01');
    }

    // ─── Prévia de palavras-chave ───────────────────────────────────────────────────────────

    public function test_preview_keywords_counts_matching_uncategorized_items(): void
    {
        $user = User::factory()->create();
        $this->itemOf($user, ['description' => 'ARROZ TIPO 1 5KG']);
        $this->itemOf($user, ['description' => 'ARROZ INTEGRAL']);
        $this->itemOf($user, ['description' => 'NOVO SABAO']);
        $this->itemOf($user, ['description' => 'ARROZ CATEGORIZADO', 'category_id' => $this->categoryOf($user)->id]);
        $this->itemOf(User::factory()->create(), ['description' => 'ARROZ DE OUTRO']);

        $this->actingAs($user)->postJson('/categories/preview-keywords', ['keywords' => 'arroz, ovo'])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'samples');

        $this->actingAs($user)->postJson('/categories/preview-keywords', ['keywords' => ''])->assertOk()->assertJsonPath('count', 0);
    }

    // ─── Auto-categorização: aviso da IA e reversão ─────────────────────────────────────────

    public function test_auto_categorize_tells_whether_ai_will_run_in_background(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->pro()->create())->postJson('/categories/auto-categorize')->assertOk()->assertJsonPath('ai_pending', true);
        $this->actingAs(User::factory()->create())->postJson('/categories/auto-categorize')->assertOk()->assertJsonPath('ai_pending', false);

        Queue::assertPushed(AiCategorizeItemsJob::class, 2);
    }

    public function test_revert_auto_categorization_only_undoes_automatic_items_of_the_user(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryOf($user);
        $keyword = $this->itemOf($user, ['category_id' => $category->id, 'categorization_source' => InvoiceItem::SOURCE_KEYWORD]);
        $ai = $this->itemOf($user, ['category_id' => $category->id, 'categorization_source' => InvoiceItem::SOURCE_AI]);
        $learned = $this->itemOf($user, ['category_id' => $category->id, 'categorization_source' => InvoiceItem::SOURCE_LEARNED]);
        $manual = $this->itemOf($user, ['category_id' => $category->id, 'categorization_source' => InvoiceItem::SOURCE_MANUAL]);
        $foreign = $this->itemOf(User::factory()->create(), ['category_id' => $category->id, 'categorization_source' => InvoiceItem::SOURCE_KEYWORD]);

        $this->actingAs($user)->get('/categories?'.self::PERIOD)->assertViewHas('autoCategorizedCount', 3);

        $this->actingAs($user)->postJson('/categories/revert-auto-categorization')->assertOk()->assertJsonPath('reverted', 3);

        foreach ([$keyword, $ai, $learned] as $item) {
            $this->assertNull($item->fresh()->category_id);
            $this->assertNull($item->fresh()->categorization_source);
        }
        $this->assertSame($category->id, $manual->fresh()->category_id);
        $this->assertSame($category->id, $foreign->fresh()->category_id);
    }

    // ─── Mesclar ───────────────────────────────────────────────────────────────────────────

    public function test_merge_moves_items_rules_keywords_and_budget_then_deletes_the_source(): void
    {
        $user = User::factory()->create();
        $source = $this->categoryOf($user, ['name' => 'Origem', 'keywords' => ['ARROZ', 'feijao']]);
        $target = $this->categoryOf($user, ['name' => 'Destino', 'keywords' => ['Arroz', 'leite']]);
        $itemA = $this->itemOf($user, ['category_id' => $source->id]);
        $itemB = $this->itemOf($user, ['category_id' => $source->id]);
        ItemCategoryRule::create(['user_id' => $user->id, 'description_key' => 'arroz', 'category_id' => $source->id, 'source' => ItemCategoryRule::SOURCE_MANUAL]);
        Budget::factory()->create(['user_id' => $user->id, 'category_id' => $source->id, 'amount' => 100]);

        $this->actingAs($user)->postJson("/categories/{$source->id}/merge", ['target_id' => $target->id])
            ->assertOk()->assertJsonPath('moved', 2);

        $this->assertModelMissing($source);
        $this->assertSame($target->id, $itemA->fresh()->category_id);
        $this->assertSame($target->id, $itemB->fresh()->category_id);
        $this->assertDatabaseHas('item_category_rules', ['description_key' => 'arroz', 'category_id' => $target->id]);
        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category_id' => $target->id, 'amount' => 100]);
        $this->assertEqualsCanonicalizing(['Arroz', 'leite', 'feijao'], $target->fresh()->keywords);
    }

    public function test_merge_flushes_dashboard_cache(): void
    {
        $user = User::factory()->create();
        $source = $this->categoryOf($user, ['name' => 'Origem']);
        $target = $this->categoryOf($user, ['name' => 'Destino']);
        $before = DashboardService::cacheVersion($user->id);

        $this->actingAs($user)->postJson("/categories/{$source->id}/merge", ['target_id' => $target->id])->assertOk();

        $this->assertNotSame($before, DashboardService::cacheVersion($user->id));
    }

    public function test_merge_drops_the_source_budget_when_target_already_has_one(): void
    {
        $user = User::factory()->create();
        $source = $this->categoryOf($user);
        $target = $this->categoryOf($user);
        Budget::factory()->create(['user_id' => $user->id, 'category_id' => $source->id, 'amount' => 100]);
        Budget::factory()->create(['user_id' => $user->id, 'category_id' => $target->id, 'amount' => 500]);

        $this->actingAs($user)->postJson("/categories/{$source->id}/merge", ['target_id' => $target->id])->assertOk();

        $this->assertDatabaseCount('budgets', 1);
        $this->assertDatabaseHas('budgets', ['category_id' => $target->id, 'amount' => 500]);
    }

    public function test_merge_validates_target_and_ownership(): void
    {
        $user = User::factory()->create();
        $source = $this->categoryOf($user);
        $foreign = $this->categoryOf(User::factory()->create());
        $system = Category::factory()->create(['user_id' => null]);

        $this->actingAs($user)->postJson("/categories/{$source->id}/merge", ['target_id' => $source->id])->assertStatus(422);
        $this->actingAs($user)->postJson("/categories/{$source->id}/merge", ['target_id' => $foreign->id])->assertStatus(422);
        $this->actingAs($user)->postJson("/categories/{$foreign->id}/merge", ['target_id' => $source->id])->assertNotFound();
        $this->actingAs($user)->postJson("/categories/{$system->id}/merge", ['target_id' => $source->id])->assertForbidden();
        $this->assertModelExists($source);
    }

    public function test_api_merge_and_revert_and_preview(): void
    {
        $user = User::factory()->create();
        $source = $this->categoryOf($user);
        $target = $this->categoryOf($user);
        $this->itemOf($user, ['category_id' => $source->id, 'categorization_source' => InvoiceItem::SOURCE_AI, 'description' => 'ARROZ']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/categories/preview-keywords', ['keywords' => 'arroz'])->assertOk()->assertJsonPath('data.count', 0);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/categories/{$source->id}/merge", ['target_id' => $target->id])->assertOk()->assertJsonPath('data.moved', 1);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/categories/revert-auto-categorization')->assertOk()->assertJsonPath('data.reverted', 1);
    }

    public function test_api_index_exposes_lifetime_count_delta_key_and_uncategorized_meta(): void
    {
        $user = User::factory()->create();
        $category = $this->categoryOf($user);
        $this->itemOf($user, ['category_id' => $category->id, 'total_price' => 60]);
        $this->itemOf($user, ['total_price' => 15]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/categories?'.self::PERIOD)->assertOk();

        $response->assertJsonPath('data.0.total_items_count', 1)
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('meta.uncategorizedCount', 1)
            ->assertJsonPath('meta.uncategorizedTotal', 15)
            ->assertJsonPath('meta.autoCategorizedCount', 0);
        $this->assertArrayHasKey('delta_pct', $response->json('data.0'));
        $this->assertNull($response->json('data.0.delta_pct'));

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/categories?start_date=2026-06-20&end_date=2026-06-01')
            ->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_keyword_ai_suggestion_button_is_only_shown_to_pro_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/categories')
            ->assertOk()->assertDontSee('suggest-keywords-new', false)->assertDontSee('suggest-keywords-edit', false);

        $this->actingAs(User::factory()->pro()->create())->get('/categories')
            ->assertOk()->assertSee('suggest-keywords-new', false)->assertSee('suggest-keywords-edit', false);
    }
}
