<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function issuerBoughtBy(User $user, array $attributes = []): Issuer
    {
        $issuer = Issuer::factory()->create($attributes);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        return $issuer;
    }

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/issuers')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200);
    }

    public function test_index_excludes_issuers_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownIssuer = Issuer::factory()->create();
        $otherIssuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $ownIssuer->id]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $otherIssuer->id]);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('records', function ($records) use ($ownIssuer, $otherIssuer) {
                $collection = $records->getCollection();

                return $collection->contains('id', $ownIssuer->id)
                    && ! $collection->contains('id', $otherIssuer->id);
            });
    }

    public function test_index_returns_purchase_stats_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 40.00]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 60.00]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id, 'total_amount' => 999.00]);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('summary', fn ($summary) => $summary['total_spent'] === 100.0 && $summary['invoices_count'] === 2 && $summary['average_ticket'] === 50.0)
            ->assertViewHas('records', function ($records) use ($issuer) {
                $found = $records->getCollection()->firstWhere('id', $issuer->id);

                return $found->purchase_count === 2 && (float) $found->total_spent === 100.0;
            });
    }

    public function test_index_falls_back_to_official_name_when_no_nickname_set(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Nome Oficial Ltda']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('records', function ($records) use ($issuer) {
                $found = $records->getCollection()->firstWhere('id', $issuer->id);

                return $found->nickname === null;
            });
    }

    public function test_index_shows_nickname_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        IssuerNickname::create(['user_id' => $other->id, 'issuer_id' => $issuer->id, 'nickname' => 'Apelido do Outro']);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertStatus(200)
            ->assertViewHas('records', function ($records) use ($issuer) {
                $found = $records->getCollection()->firstWhere('id', $issuer->id);

                return $found->nickname === null;
            });
    }

    public function test_update_nickname_redirects_unauthenticated_user(): void
    {
        $issuer = Issuer::factory()->create();

        $this->put("/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertRedirect('/login');
    }

    public function test_update_nickname_sets_custom_nickname(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user, ['name' => 'Nome Oficial Ltda']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria da Esquina'])
            ->assertStatus(200)
            ->assertJson(['nickname' => 'Padaria da Esquina', 'display_name' => 'Padaria da Esquina']);

        $this->assertDatabaseHas('issuer_nicknames', [
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'nickname' => 'Padaria da Esquina',
        ]);
    }

    public function test_update_nickname_clears_nickname_and_falls_back_to_official_name(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user, ['name' => 'Nome Oficial Ltda']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Apelido']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => ''])
            ->assertStatus(200)
            ->assertJson(['nickname' => null, 'display_name' => 'Nome Oficial Ltda']);

        $this->assertDatabaseMissing('issuer_nicknames', [
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
        ]);
    }

    public function test_update_nickname_rejects_duplicate_within_same_user(): void
    {
        $user = User::factory()->create();
        $issuerA = $this->issuerBoughtBy($user);
        $issuerB = $this->issuerBoughtBy($user);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercado']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuerB->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nickname');
    }

    public function test_update_nickname_allows_same_nickname_for_different_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuerA = $this->issuerBoughtBy($user);
        $issuerB = $this->issuerBoughtBy($user);
        IssuerNickname::create(['user_id' => $other->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercado']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuerB->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(200)
            ->assertJson(['nickname' => 'Mercado']);
    }

    public function test_update_nickname_allows_resaving_same_nickname_for_same_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Mercado']);

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => 'Mercado'])
            ->assertStatus(200)
            ->assertJson(['nickname' => 'Mercado']);
    }

    public function test_update_nickname_returns_404_for_issuer_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user)
            ->putJson("/issuers/{$issuer->id}/nickname", ['nickname' => 'Padaria'])
            ->assertStatus(404);

        $this->assertDatabaseMissing('issuer_nicknames', ['issuer_id' => $issuer->id]);
    }

    public function test_detail_returns_404_for_issuer_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => User::factory()->create()->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)->get("/issuers/detail/{$issuer->id}")->assertStatus(404);
    }

    public function test_detail_opens_for_issuer_the_user_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user);

        $this->actingAs($user)->get("/issuers/detail/{$issuer->id}")->assertStatus(200);
    }

    public function test_toggle_favorite_returns_404_for_issuer_the_user_never_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->actingAs($user)->postJson("/issuers/{$issuer->id}/favorite")->assertStatus(404);

        $this->assertDatabaseMissing('favorite_issuers', ['issuer_id' => $issuer->id]);
    }

    public function test_toggle_favorite_marks_issuer_the_user_bought_from(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user);

        $this->actingAs($user)
            ->postJson("/issuers/{$issuer->id}/favorite")
            ->assertStatus(200)
            ->assertJson(['is_favorite' => true]);
    }

    public function test_index_lists_favorites_first(): void
    {
        $user = User::factory()->create();
        $first = $this->issuerBoughtBy($user, ['name' => 'Aaa Mercado']);
        $second = $this->issuerBoughtBy($user, ['name' => 'Zzz Padaria']);
        $user->favoriteIssuers()->attach($second->id);

        $this->actingAs($user)
            ->get('/issuers')
            ->assertViewHas('records', fn ($records) => $records->getCollection()->pluck('id')->all() === [$second->id, $first->id]);
    }

    public function test_index_search_matches_official_name_nickname_and_cnpj_across_pages(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 20) as $i) {
            $this->issuerBoughtBy($user, ['name' => sprintf('Loja %02d', $i), 'cnpj' => sprintf('1111111100%04d', $i)]);
        }
        $target = $this->issuerBoughtBy($user, ['name' => 'Zzz Atacadao', 'cnpj' => '12345678000190']);
        $nicknamed = $this->issuerBoughtBy($user, ['name' => 'Outra Razao Ltda']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $nicknamed->id, 'nickname' => 'Feira do Bairro']);

        $ids = fn (string $q) => $this->actingAs($user)->get('/issuers?q='.urlencode($q))
            ->viewData('records')->getCollection()->pluck('id')->all();

        $this->assertSame([$target->id], $ids('atacadao'));
        $this->assertSame([$target->id], $ids('12.345.678/0001-90'));
        $this->assertSame([$nicknamed->id], $ids('feira'));
        $this->assertSame([], $ids('inexistente'));
    }

    public function test_index_search_never_reveals_issuers_of_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->issuerBoughtBy($other, ['name' => 'Segredo Ltda']);

        $this->actingAs($user)
            ->get('/issuers?q=Segredo')
            ->assertViewHas('records', fn ($records) => $records->isEmpty());
    }

    private function indexIds(User $user, string $query): array
    {
        return $this->actingAs($user)->get('/issuers'.$query)->viewData('records')->getCollection()->pluck('id')->all();
    }

    public function test_index_sorts_by_spent_visits_and_last_purchase(): void
    {
        $user = User::factory()->create();
        $bigSpender = Issuer::factory()->create(['name' => 'A Caro']);
        $frequent = Issuer::factory()->create(['name' => 'B Frequente']);
        $recent = Issuer::factory()->create(['name' => 'C Recente']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $bigSpender->id, 'total_amount' => 900, 'issued_at' => '2026-01-01']);
        Invoice::factory()->count(3)->create(['user_id' => $user->id, 'issuer_id' => $frequent->id, 'total_amount' => 10, 'issued_at' => '2026-02-01']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $recent->id, 'total_amount' => 5, 'issued_at' => '2026-09-01']);

        $this->assertSame($bigSpender->id, $this->indexIds($user, '?sort=spent')[0]);
        $this->assertSame($frequent->id, $this->indexIds($user, '?sort=visits')[0]);
        $this->assertSame($recent->id, $this->indexIds($user, '?sort=last')[0]);
    }

    public function test_index_rejects_unknown_sort(): void
    {
        $this->actingAs(User::factory()->create())->get('/issuers?sort=drop_table')->assertSessionHasErrors('sort');
    }

    public function test_index_exposes_last_purchase_at_per_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'issued_at' => '2026-03-10 10:00:00']);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'issued_at' => '2026-05-20 10:00:00']);

        $this->actingAs($user)->get('/issuers')->assertViewHas('records', fn ($records) => str_starts_with(
            (string) $records->getCollection()->first()->last_purchase_at,
            '2026-05-20'
        ));
    }

    public function test_index_filters_only_favorites(): void
    {
        $user = User::factory()->create();
        $fav = $this->issuerBoughtBy($user);
        $this->issuerBoughtBy($user);
        $user->favoriteIssuers()->attach($fav->id);

        $this->assertSame([$fav->id], $this->indexIds($user, '?favorites=1'));
    }

    public function test_index_filters_by_city_and_lists_only_own_cities(): void
    {
        $user = User::factory()->create();
        $sp = $this->issuerBoughtBy($user, ['city' => 'Sao Paulo']);
        $this->issuerBoughtBy($user, ['city' => 'Curitiba']);
        $this->issuerBoughtBy(User::factory()->create(), ['city' => 'Manaus']);

        $this->assertSame([$sp->id], $this->indexIds($user, '?city=Sao+Paulo'));

        $this->actingAs($user)->get('/issuers')
            ->assertViewHas('cities', ['Curitiba', 'Sao Paulo']);
    }

    public function test_index_summary_reports_most_visited_issuer_using_nickname(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Razao Social Ltda']);
        Invoice::factory()->count(2)->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        $this->issuerBoughtBy($user);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'nickname' => 'Mercadinho']);

        $this->actingAs($user)->get('/issuers')->assertViewHas('summary', fn ($summary) => $summary['top_issuer'] === ['name' => 'Mercadinho', 'visits' => 2]);
    }

    private function insightsOf(User $user, Issuer $issuer): array
    {
        return $this->actingAs($user)->get("/issuers/detail/{$issuer->id}")->assertStatus(200)->viewData('insights');
    }

    public function test_detail_insights_report_ticket_frequency_and_full_monthly_series(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 100, 'issued_at' => now()->subDays(20)]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 50, 'issued_at' => now()->subDays(10)]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 60, 'issued_at' => now()]);

        $insights = $this->insightsOf($user, $issuer);

        $this->assertSame(70.0, $insights['average_ticket']);
        $this->assertSame(10, $insights['visit_interval_days']);
        $this->assertCount(12, $insights['monthly']);
        $this->assertEqualsWithDelta(210.0, array_sum(array_column($insights['monthly'], 'total')), 0.001);
    }

    public function test_detail_insights_frequency_is_null_with_a_single_purchase(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user);

        $this->assertNull($this->insightsOf($user, $issuer)['visit_interval_days']);
    }

    public function test_detail_top_products_rank_by_total_and_show_last_price_variation(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $old = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'issued_at' => now()->subDays(10)]);
        $new = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'issued_at' => now()]);
        foreach ([[$old, 10.0], [$new, 12.0]] as [$invoice, $price]) {
            InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => 'CAFE', 'quantity' => 1, 'unit_price' => $price, 'total_price' => $price]);
        }
        InvoiceItem::factory()->create(['invoice_id' => $new->id, 'description' => 'BALA', 'quantity' => 1, 'unit_price' => 1, 'total_price' => 1]);

        $top = $this->insightsOf($user, $issuer)['top_products'];

        $this->assertSame(['CAFE', 'BALA'], array_column($top, 'name'));
        $this->assertSame(2, $top[0]['purchases']);
        $this->assertSame(22.0, $top[0]['total']);
        $this->assertSame(12.0, $top[0]['last_price']);
        $this->assertSame(20.0, $top[0]['variation_pct']);
        $this->assertNull($top[1]['variation_pct']);
    }

    public function test_detail_categories_share_uses_category_and_fallback(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        $category = Category::factory()->create(['user_id' => $user->id, 'name' => 'Padaria']);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => $category->id, 'total_price' => 30]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => null, 'total_price' => 10]);

        $categories = $this->insightsOf($user, $issuer)['categories'];

        $this->assertSame(['Padaria', 'Sem categoria'], array_column($categories, 'name'));
        $this->assertSame([75.0, 25.0], array_column($categories, 'share'));
    }

    public function test_detail_insights_ignore_other_users_purchases_at_the_same_issuer(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user);
        $foreign = Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id, 'total_amount' => 999, 'issued_at' => now()]);
        InvoiceItem::factory()->create(['invoice_id' => $foreign->id, 'description' => 'SEGREDO DO OUTRO', 'total_price' => 999]);

        $insights = $this->insightsOf($user, $issuer);

        $this->assertNotContains('SEGREDO DO OUTRO', array_column($insights['top_products'], 'name'));
        $this->assertLessThan(999, array_sum(array_column($insights['monthly'], 'total')));
    }

    public function test_detail_paginates_invoices_and_searches_by_number(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        Invoice::factory()->count(17)->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        $target = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'number' => '987654']);

        $this->actingAs($user)->get("/issuers/detail/{$issuer->id}")
            ->assertViewHas('invoices', fn ($invoices) => $invoices->count() === 15 && $invoices->total() === 18);

        $this->actingAs($user)->get("/issuers/detail/{$issuer->id}?q=987654")
            ->assertViewHas('invoices', fn ($invoices) => $invoices->pluck('id')->all() === [$target->id]);
    }

    private function purchasesAt(User $user, Issuer $issuer, array $totals): void
    {
        // do mais antigo para o mais recente
        foreach ($totals as $i => $total) {
            Invoice::factory()->create([
                'user_id' => $user->id,
                'issuer_id' => $issuer->id,
                'total_amount' => $total,
                'issued_at' => now()->subDays(count($totals) - $i),
            ]);
        }
    }

    public function test_detail_ticket_trend_compares_recent_purchases_with_previous_ones(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchasesAt($user, $issuer, [100, 100, 100, 150, 150, 150]);

        $this->assertSame(50.0, $this->insightsOf($user, $issuer)['ticket_trend_pct']);
    }

    public function test_detail_ticket_trend_is_negative_when_recent_tickets_drop(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchasesAt($user, $issuer, [200, 200, 100, 100]);

        $this->assertSame(-50.0, $this->insightsOf($user, $issuer)['ticket_trend_pct']);
    }

    public function test_detail_ticket_trend_is_null_with_fewer_than_four_purchases(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->purchasesAt($user, $issuer, [100, 100, 500]);

        $this->assertNull($this->insightsOf($user, $issuer)['ticket_trend_pct']);
    }

    public function test_detail_ticket_trend_ignores_other_users_purchases(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerBoughtBy($user);
        $this->purchasesAt(User::factory()->create(), $issuer, [1, 1, 1000, 1000]);

        $this->assertNull($this->insightsOf($user, $issuer)['ticket_trend_pct']);
    }

    private function issuerWithTopProduct(User $user): Issuer
    {
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => 'CAFE TORRADO', 'total_price' => 20]);

        return $issuer;
    }

    public function test_detail_links_to_price_comparison_for_pro_users(): void
    {
        $user = User::factory()->pro()->create();
        $issuer = $this->issuerWithTopProduct($user);

        $this->actingAs($user)->get("/issuers/detail/{$issuer->id}")
            ->assertStatus(200)
            ->assertViewHas('canComparePrices', true)
            ->assertSee(route('prices.index', ['product' => 'CAFE TORRADO']), false);
    }

    public function test_detail_hides_price_comparison_link_for_free_users(): void
    {
        $user = User::factory()->create();
        $issuer = $this->issuerWithTopProduct($user);

        $this->actingAs($user)->get("/issuers/detail/{$issuer->id}")
            ->assertStatus(200)
            ->assertViewHas('canComparePrices', false)
            ->assertDontSee('Comparar preços');
    }
}
