<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceComparisonControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/price-comparison')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/price-comparison')->assertStatus(200);
    }

    public function test_search_products_returns_empty_for_short_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/price-comparison/search-products?q=a')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_search_products_returns_matching_candidates(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $response = $this->actingAs($user)->getJson('/price-comparison/search-products?q=ARROZ');

        $response->assertStatus(200);
        $this->assertEquals('ARROZ BRANCO 5KG', $response->json('0.name'));
    }

    public function test_by_city_returns_empty_without_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/price-comparison/by-city')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_by_city_returns_ranked_results(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        $response = $this->actingAs($user)->getJson('/price-comparison/by-city?product='.urlencode('ARROZ BRANCO 5KG'));

        $response->assertStatus(200);
        $this->assertEquals('Curitiba', $response->json('0.city'));
    }

    public function test_by_issuer_returns_empty_without_city_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/price-comparison/by-issuer?product=arroz')
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
            ->getJson('/price-comparison/by-issuer?product='.urlencode('ARROZ BRANCO 5KG').'&city=Curitiba&state=PR');

        $response->assertStatus(200);
        $this->assertEquals('MERCADO X', $response->json('0.issuer_name'));
    }
}
