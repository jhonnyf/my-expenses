<?php

namespace Tests\Feature;

use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\ProductAlias;
use App\Models\ShoppingList;
use App\Models\User;
use App\Notifications\FavoriteProductPriceDropped;
use App\Services\PriceComparisonService;
use App\Services\ProductAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Página de Preços: linha do tempo (mais recentes), variação real, unidade, comparativo pelo preço ATUAL,
 * alerta de queda sem preço velho, validação e API.
 */
class PricesManagementTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(string $description, float $price, ?Issuer $issuer, int $daysAgo = 1, ?User $by = null, string $unit = 'UN'): InvoiceItem
    {
        $invoice = Invoice::factory()->create([
            'user_id' => ($by ?? User::factory()->create())->id,
            'issuer_id' => $issuer?->id,
            'issued_at' => now()->subDays($daysAgo),
        ]);

        return InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => $description, 'unit_price' => $price, 'unit' => $unit, 'quantity' => 1, 'total_price' => $price]);
    }

    private function issuer(string $name, string $city = 'Curitiba', string $state = 'PR'): Issuer
    {
        return Issuer::factory()->create(['name' => $name, 'city' => $city, 'state' => $state]);
    }

    private function history(User $user, string $product, string $extra = ''): array
    {
        return $this->actingAs($user)->getJson('/prices/history?description='.urlencode($product).$extra)->assertOk()->json();
    }

    private function pro(): User
    {
        return User::factory()->pro()->create();
    }

    // ─── Linha do tempo: as compras mais recentes ─────────────────────────────────────────────

    public function test_timeline_keeps_the_most_recent_purchases_when_history_is_long(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('Mercado A');
        foreach (range(1, 205) as $daysAgo) {
            $this->purchase('CAFE PILAO 500G', 1 + $daysAgo / 100, $issuer, $daysAgo, $user); // dias atrás maiores = mais barato... e mais antigo
        }

        $data = $this->history($user, 'CAFE PILAO 500G');

        $this->assertCount(200, $data['timeline']);
        $this->assertSame(205, $data['total_entries']);
        $this->assertTrue($data['truncated']);
        $prices = array_map('floatval', array_column($data['timeline'], 'unit_price'));
        $this->assertEquals(1 + 1 / 100, end($prices));      // a mais nova (1 dia atrás) está lá
        $this->assertEquals(1 + 200 / 100, $prices[0]);      // a mais antiga mostrada é a de 200 dias
        $this->assertGreaterThan($prices[0] - 0.001, max($prices));
        $this->assertEquals(1 + 205 / 100, $data['summary']['max_price']); // o resumo cobre TODO o histórico
    }

    public function test_timeline_is_ordered_oldest_to_newest_and_ignores_other_users_and_zero_prices(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('Mercado A');
        $this->purchase('LEITE 1L', 5, $issuer, 10, $user);
        $this->purchase('LEITE 1L', 6, $issuer, 2, $user);
        $this->purchase('LEITE 1L', 0, $issuer, 5, $user);                         // brinde: não conta
        $this->purchase('LEITE 1L', 99, $issuer, 1);                                // de outro usuário

        $data = $this->history($user, 'LEITE 1L');

        $this->assertEquals([5, 6], array_map('floatval', array_column($data['timeline'], 'unit_price')));
        $this->assertSame(2, $data['total_entries']);
        $this->assertEquals(5, $data['summary']['min_price']);
    }

    public function test_timeline_shows_purchases_of_invoices_without_issuer(): void
    {
        $user = $this->pro();
        $this->purchase('PAO 500G', 8, null, 3, $user);

        $this->assertSame('Emissor não identificado', $this->history($user, 'PAO 500G')['timeline'][0]['issuer_name']);
    }

    // ─── Variação de verdade ────────────────────────────────────────────────────────────────

    public function test_summary_reports_real_change_median_and_spread(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('Mercado A');
        $this->purchase('ARROZ 5KG', 20, $issuer, 120, $user);
        $this->purchase('ARROZ 5KG', 10, $issuer, 45, $user);
        $this->purchase('ARROZ 5KG', 15, $issuer, 20, $user);
        $this->purchase('ARROZ 5KG', 12, $issuer, 2, $user);

        $summary = $this->history($user, 'ARROZ 5KG')['summary'];

        $this->assertEquals(12, $summary['last_price']);
        $this->assertEquals(15, $summary['previous_price']);
        $this->assertEquals(-20.0, $summary['change_pct']);          // 15 → 12
        $this->assertSame('down', $summary['trend']);
        $this->assertEquals(20.0, $summary['change_30d_pct']);       // 10 (há 45 dias) → 12
        $this->assertEquals(-40.0, $summary['change_90d_pct']);      // 20 (há 120 dias) → 12
        $this->assertEquals(13.5, $summary['median_price']);         // (12 + 15) ÷ 2
        $this->assertEquals(100.0, $summary['spread_pct']);          // (20 − 10) ÷ 10
        $this->assertEquals(100.0, $summary['variation_pct']);       // mantido por compatibilidade
    }

    public function test_summary_has_null_changes_with_a_single_purchase(): void
    {
        $user = $this->pro();
        $this->purchase('SABAO 1KG', 8, $this->issuer('A'), 3, $user);

        $summary = $this->history($user, 'SABAO 1KG')['summary'];

        $this->assertEquals(8, $summary['last_price']);
        $this->assertNull($summary['previous_price']);
        $this->assertNull($summary['change_pct']);
        $this->assertNull($summary['trend']);
        $this->assertNull($summary['change_30d_pct']);
    }

    public function test_monthly_series_groups_min_avg_and_max_per_month(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('A');
        $this->purchase('FEIJAO 1KG', 6, $issuer, 40, $user);
        $this->purchase('FEIJAO 1KG', 8, $issuer, 41, $user);
        $this->purchase('FEIJAO 1KG', 10, $issuer, 2, $user);

        $monthly = collect($this->history($user, 'FEIJAO 1KG')['monthly']);

        $this->assertSame(now()->subDays(2)->format('Y-m'), $monthly->last()['month']);
        $this->assertEquals(10, $monthly->last()['avg']);
        $this->assertSame(3, $monthly->sum('count'));
        $old = $monthly->firstWhere('month', now()->subDays(40)->format('Y-m'));
        $this->assertTrue($old['min'] <= $old['avg'] && $old['avg'] <= $old['max']);
    }

    // ─── Unidade ─────────────────────────────────────────────────────────────────────────────

    public function test_history_and_comparison_can_be_restricted_to_one_unit(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('Feira');
        $this->purchase('BANANA PRATA', 6, $issuer, 3, $user, 'KG');
        $this->purchase('BANANA PRATA', 2, $issuer, 5, $user, 'UN');
        $this->purchase('BANANA PRATA', 7, $issuer, 9, $user, 'KG');

        $all = $this->history($user, 'BANANA PRATA');
        $kg = $this->history($user, 'BANANA PRATA', '&unit=KG');

        $this->assertSame([['unit' => 'KG', 'sample_count' => 2], ['unit' => 'UN', 'sample_count' => 1]], $all['units']);
        $this->assertSame(3, $all['total_entries']);
        $this->assertSame(2, $kg['total_entries']);
        $this->assertEquals(6, $kg['summary']['min_price']);

        $units = $this->actingAs($user)->getJson('/prices/units?product='.urlencode('BANANA PRATA'))->assertOk()->json();
        $this->assertSame(['KG', 'UN'], array_column($units, 'unit'));

        $byIssuer = $this->actingAs($user)->getJson('/prices/by-issuer?product='.urlencode('BANANA PRATA').'&city=Curitiba&state=PR&unit=UN')->assertOk()->json();
        $this->assertEquals(2, $byIssuer[0]['price']);
    }

    // ─── Comparativo pelo preço atual ─────────────────────────────────────────────────────────

    public function test_by_issuer_uses_the_latest_price_of_each_issuer_and_sorts_current_before_old(): void
    {
        $user = $this->pro();
        $a = $this->issuer('Mercado A');
        $b = $this->issuer('Mercado B');
        $c = $this->issuer('Mercado C');
        $this->purchase('OVO 20UN', 5, $a, 60);     // já foi barato...
        $this->purchase('OVO 20UN', 12, $a, 2);     // ...mas hoje custa 12
        $this->purchase('OVO 20UN', 9, $b, 10);
        $this->purchase('OVO 20UN', 3, $c, 300);    // muito barato, porém antigo
        $this->purchase('OVO 20UN', 1, $this->issuer('Outra Cidade', 'Recife', 'PE'), 1);

        $rows = $this->actingAs($user)->getJson('/prices/by-issuer?product='.urlencode('OVO 20UN').'&city=Curitiba&state=PR')->assertOk()->json();

        $this->assertSame(['Mercado B', 'Mercado A', 'Mercado C'], array_column($rows, 'issuer_name'));
        $this->assertEquals([9, 12, 3], array_map('floatval', array_column($rows, 'price')));
        $this->assertSame([false, false, true], array_column($rows, 'is_stale'));
        $this->assertEquals(5, $rows[1]['min_price']);           // referência histórica continua
        $this->assertSame(2, $rows[1]['sample_count']);
        $this->assertNull($rows[0]['distance_km']);
    }

    public function test_by_city_ranks_by_the_cheapest_current_price_and_flags_the_users_city(): void
    {
        $user = $this->pro();
        $user->profile()->create(['cpf_cnpj_hash' => 'x', 'cidade' => 'Curitiba', 'estado' => 'PR']);
        $ctbA = $this->issuer('Curitiba A', 'Curitiba', 'PR');
        $ctbB = $this->issuer('Curitiba B', 'Curitiba', 'PR');
        $sp = $this->issuer('Paulista', 'São Paulo', 'SP');
        $old = $this->issuer('Antigo', 'Recife', 'PE');
        $this->purchase('LEITE 1L', 6, $ctbA, 4);
        $this->purchase('LEITE 1L', 5, $ctbB, 6);
        $this->purchase('LEITE 1L', 3, $ctbB, 100);       // histórico mais barato, mas só conta o mais recente de B
        $this->purchase('LEITE 1L', 4, $sp, 3);
        $this->purchase('LEITE 1L', 1, $old, 400);

        $rows = $this->actingAs($user)->getJson('/prices/by-city?product='.urlencode('LEITE 1L'))->assertOk()->json();

        $this->assertSame(['São Paulo', 'Curitiba', 'Recife'], array_column($rows, 'city'));
        $curitiba = collect($rows)->firstWhere('city', 'Curitiba');
        $this->assertEquals(5, $curitiba['price']);                  // menor entre os mais recentes: A=6, B=5
        $this->assertSame('Curitiba B', $curitiba['cheapest_issuer_name']);
        $this->assertSame(2, $curitiba['issuer_count']);
        $this->assertEquals(3, $curitiba['min_price']);              // histórico
        $this->assertSame(3, $curitiba['sample_count']);
        $this->assertTrue($curitiba['is_user_city']);
        $this->assertFalse(collect($rows)->firstWhere('city', 'São Paulo')['is_user_city']);
        $this->assertTrue(collect($rows)->firstWhere('city', 'Recife')['is_stale']);
    }

    public function test_zero_prices_and_issuers_without_city_do_not_distort_the_ranking(): void
    {
        $user = $this->pro();
        $this->purchase('MACARRAO 500G', 0, $this->issuer('Brinde'), 2);
        $this->purchase('MACARRAO 500G', 4, $this->issuer('Normal'), 2);
        $this->purchase('MACARRAO 500G', 2, Issuer::factory()->create(['city' => null, 'state' => null]), 2);

        $rows = $this->actingAs($user)->getJson('/prices/by-city?product='.urlencode('MACARRAO 500G'))->assertOk()->json();

        $this->assertCount(1, $rows);
        $this->assertEquals(4, $rows[0]['price']);
    }

    public function test_comparison_matches_the_users_alias_across_other_users_descriptions(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('Mercado A');
        $this->purchase('COCA COLA 350ML LT', 5, $issuer, 3);
        $other = $this->issuer('Mercado B');
        $this->purchase('REFRIG COCA-COLA 350', 4, $other, 2);
        $this->purchase('COCA COLA 350ML LT', 9, $this->issuer('Mercado C'), 2, null);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA COLA 350ML LT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA-COLA 350', 'canonical_name' => 'Coca-Cola 350ml']);

        $rows = $this->actingAs($user)->getJson('/prices/by-issuer?product='.urlencode('Coca-Cola 350ml').'&city=Curitiba&state=PR')->assertOk()->json();

        $this->assertSame(['Mercado B', 'Mercado A', 'Mercado C'], array_column($rows, 'issuer_name'));
    }

    public function test_descriptions_for_excludes_a_name_the_user_moved_to_another_canonical(): void
    {
        $user = User::factory()->create();
        ProductAlias::create(['user_id' => $user->id, 'description' => 'LEITE COMUM', 'canonical_name' => 'Leite']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'LEITE', 'canonical_name' => 'Leite Integral']);
        $service = app(ProductAliasService::class);

        $this->assertEqualsCanonicalizing(['LEITE COMUM', 'Leite'], $service->descriptionsFor('Leite', $user->id));
        $this->assertEqualsCanonicalizing(['LEITE', 'Leite Integral'], $service->descriptionsFor('Leite Integral', $user->id));
        $this->assertSame(['ARROZ'], $service->descriptionsFor('ARROZ', $user->id));
        $this->assertSame([], $service->descriptionsFor('LEITE', $user->id));
    }

    public function test_issuer_nickname_is_used_in_the_comparison(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('RAZAO SOCIAL LTDA');
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Mercadinho']);
        $this->purchase('AGUA 1L', 2, $issuer, 2);

        $rows = $this->actingAs($user)->getJson('/prices/by-issuer?product='.urlencode('AGUA 1L').'&city=Curitiba&state=PR')->json();

        $this->assertSame('Mercadinho', $rows[0]['issuer_name']);
    }

    // ─── Busca de produtos ───────────────────────────────────────────────────────────────────

    public function test_product_search_shows_the_current_lowest_price_and_flags_old_only_products(): void
    {
        $user = $this->pro();
        $issuer = $this->issuer('A');
        $this->purchase('CAFE TRES CORACOES', 20, $issuer, 300);
        $this->purchase('CAFE TRES CORACOES', 22, $issuer, 5);
        $this->purchase('CAFE ANTIGO 500G', 10, $issuer, 400);
        $this->purchase('CAFE GRATIS', 0, $issuer, 2);

        $rows = collect($this->actingAs($user)->getJson('/prices/search?q=cafe')->assertOk()->json())->keyBy('name');

        $this->assertEquals(22, $rows['CAFE TRES CORACOES']['current_min_price']);
        $this->assertEquals(20, $rows['CAFE TRES CORACOES']['min_price']);
        $this->assertNull($rows['CAFE ANTIGO 500G']['current_min_price']);
        $this->assertNotNull($rows['CAFE ANTIGO 500G']['last_purchased_at']);
        $this->assertFalse($rows->has('CAFE GRATIS'));
    }

    public function test_search_strips_like_wildcards_and_short_terms(): void
    {
        $user = $this->pro();
        $this->purchase('CAFE 500G', 20, $this->issuer('A'), 2, $user);

        $this->actingAs($user)->getJson('/prices/search?q='.urlencode('%%'))->assertOk()->assertExactJson([]);
        $this->actingAs($user)->getJson('/prices/search?q='.urlencode('c%'))->assertOk()->assertExactJson([]);
        $this->actingAs($user)->getJson('/prices/search?q=cafe')->assertOk()->assertJsonCount(1);
    }

    // ─── Alerta de queda de preço (só preço atual) ───────────────────────────────────────────

    public function test_cheapest_offer_ignores_old_prices_and_only_the_latest_price_of_each_issuer_counts(): void
    {
        $user = User::factory()->create();
        $a = $this->issuer('Mercado A');
        $b = $this->issuer('Mercado B');
        $this->purchase('ARROZ 5KG', 10, $a, 200);   // antigo e barato: não vale
        $this->purchase('ARROZ 5KG', 4, $b, 100);     // antigo
        $this->purchase('ARROZ 5KG', 8, $a, 5);      // atual (o mais recente de A)
        $this->purchase('ARROZ 5KG', 9, $b, 3);      // atual

        $offer = app(PriceComparisonService::class)->cheapestOffer('ARROZ 5KG', $user->id);

        $this->assertEquals(8, $offer['price']);
        $this->assertSame('Mercado A', $offer['issuer_name']);
    }

    public function test_cheapest_offer_is_null_when_every_price_is_old(): void
    {
        $this->purchase('ARROZ 5KG', 4, $this->issuer('Mercado B'), 100);

        $this->assertNull(app(PriceComparisonService::class)->cheapestOffer('ARROZ 5KG', User::factory()->create()->id));
    }

    public function test_drop_alert_does_not_fire_from_an_old_cheap_price(): void
    {
        Notification::fake();
        $user = User::factory()->pro()->create();
        FavoriteProduct::create(['user_id' => $user->id, 'canonical_name' => 'ARROZ 5KG', 'last_notified_price' => 20]);
        $this->purchase('ARROZ 5KG', 5, $this->issuer('Mercado B'), 200);   // caiu 75%, mas há 200 dias

        $this->artisan('prices:check-favorite-drops')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertEquals(20, FavoriteProduct::first()->last_notified_price);   // e não sobrescreve a referência
    }

    public function test_drop_alert_fires_from_a_current_price(): void
    {
        Notification::fake();
        $user = User::factory()->pro()->create();
        FavoriteProduct::create(['user_id' => $user->id, 'canonical_name' => 'ARROZ 5KG', 'last_notified_price' => 20]);
        $this->purchase('ARROZ 5KG', 5, $this->issuer('Mercado B'), 4);

        $this->artisan('prices:check-favorite-drops')->assertSuccessful();

        Notification::assertSentTo($user, FavoriteProductPriceDropped::class);
    }

    // ─── Validação e telas ────────────────────────────────────────────────────────────────────

    public function test_queries_validate_lengths_with_json_errors(): void
    {
        $user = $this->pro();

        $this->actingAs($user)->getJson('/prices/search?q='.str_repeat('a', 101))->assertStatus(422)->assertJsonValidationErrors('q');
        $this->actingAs($user)->getJson('/prices/history?description='.str_repeat('a', 256))->assertStatus(422)->assertJsonValidationErrors('description');
        $this->actingAs($user)->getJson('/prices/by-city?product=x&unit='.str_repeat('a', 21))->assertStatus(422)->assertJsonValidationErrors('unit');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/price-comparison/by-issuer?product=x&city='.str_repeat('a', 101).'&state=PR')->assertStatus(422);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/price-history/timeline?description='.str_repeat('a', 256))->assertStatus(422);
    }

    public function test_page_receives_shopping_lists_and_favorite_products(): void
    {
        $user = $this->pro();
        ShoppingList::create(['user_id' => $user->id, 'name' => 'Feira']);
        ShoppingList::create(['user_id' => User::factory()->create()->id, 'name' => 'De outro']);
        FavoriteProduct::create(['user_id' => $user->id, 'canonical_name' => 'ARROZ 5KG']);

        $this->actingAs($user)->get('/prices')->assertOk()
            ->assertViewHas('shoppingLists', fn ($lists) => $lists->pluck('name')->all() === ['Feira'])
            ->assertViewHas('favoriteProducts', fn ($names) => $names->all() === ['ARROZ 5KG'])
            ->assertSee('addToListModal', false)
            ->assertSee(route('prices.units'), false);
    }

    public function test_pro_only_and_api_endpoints(): void
    {
        $this->actingAs(User::factory()->create())->get('/prices/units?product=x')->assertRedirect(route('subscription.upgrade'));
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/price-comparison/units?product=x')->assertStatus(402);

        $user = $this->pro();
        $this->purchase('FARINHA 1KG', 5, $this->issuer('A'), 2, $user, 'KG');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/price-comparison/units?product='.urlencode('FARINHA 1KG'))
            ->assertOk()->assertJsonPath('data.0.unit', 'KG')->assertJsonPath('data.0.sample_count', 1);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/price-history/timeline?description='.urlencode('FARINHA 1KG').'&unit=KG')
            ->assertOk()->assertJsonPath('data.summary.last_price', 5)->assertJsonPath('data.total_entries', 1);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/price-comparison/by-city?product='.urlencode('FARINHA 1KG'))
            ->assertOk()->assertJsonPath('data.0.price', 5)->assertJsonPath('data.0.is_stale', false);
    }
}
