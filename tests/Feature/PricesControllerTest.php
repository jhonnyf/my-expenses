<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/prices')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/prices')->assertStatus(200);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/prices/search?q=a')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_search_returns_matching_candidates(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $response = $this->actingAs($user)->getJson('/prices/search?q=ARROZ');

        $response->assertStatus(200);
        $this->assertEquals('ARROZ BRANCO 5KG', $response->json('0.name'));
    }

    public function test_history_returns_empty_for_blank_description(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/prices/history')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_history_returns_timeline_for_own_purchases(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'OLEO DE SOJA 900ML', 'unit_price' => 7.50]);

        $response = $this->actingAs($user)->getJson('/prices/history?description='.urlencode('OLEO DE SOJA 900ML'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('timeline'));
        $this->assertEquals(7.50, $response->json('summary.min_price'));
    }

    public function test_history_returns_empty_timeline_for_product_never_purchased_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        InvoiceItem::factory()->for(Invoice::factory()->for($other)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $response = $this->actingAs($user)->getJson('/prices/history?description='.urlencode('ARROZ BRANCO 5KG'));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('timeline'));
        $this->assertEquals(0, $response->json('summary.min_price'));
    }

    public function test_by_city_returns_empty_without_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/prices/by-city')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_by_city_returns_ranked_results(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        $response = $this->actingAs($user)->getJson('/prices/by-city?product='.urlencode('ARROZ BRANCO 5KG'));

        $response->assertStatus(200);
        $this->assertEquals('Curitiba', $response->json('0.city'));
    }

    public function test_by_issuer_returns_empty_without_city_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/prices/by-issuer?product=arroz')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_by_issuer_returns_ranked_results(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'MERCADO X', 'city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        $response = $this->actingAs($user)
            ->getJson('/prices/by-issuer?product='.urlencode('ARROZ BRANCO 5KG').'&city=Curitiba&state=PR');

        $response->assertStatus(200);
        $this->assertEquals('MERCADO X', $response->json('0.issuer_name'));
    }

    public function test_legacy_price_history_url_redirects_to_prices_preserving_query_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/price-history?q=arroz')
            ->assertRedirect('/prices?q=arroz');
    }

    public function test_legacy_price_comparison_url_redirects_to_prices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/price-comparison')
            ->assertRedirect('/prices');
    }

    public function test_legacy_price_history_url_redirects_unauthenticated_user_to_login(): void
    {
        $this->get('/price-history')->assertRedirect('/login');
    }
}
