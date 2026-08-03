<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceComparisonControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_products_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/price-comparison/search-products?q=arroz')->assertStatus(401);
    }

    public function test_search_products_returns_matching_candidates(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/price-comparison/search-products?q=ARROZ');

        $response->assertStatus(200);
        $this->assertEquals('ARROZ BRANCO 5KG', $response->json('data.0.name'));
    }

    public function test_by_city_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/price-comparison/by-city?product=arroz')->assertStatus(401);
    }

    public function test_by_city_returns_ranked_results(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/price-comparison/by-city?product='.urlencode('ARROZ BRANCO 5KG'));

        $response->assertStatus(200);
        $this->assertEquals('Curitiba', $response->json('data.0.city'));
    }

    public function test_by_issuer_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/price-comparison/by-issuer?product=arroz&city=Curitiba&state=PR')->assertStatus(401);
    }

    public function test_by_issuer_returns_ranked_results(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'MERCADO X', 'city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/price-comparison/by-issuer?product='.urlencode('ARROZ BRANCO 5KG').'&city=Curitiba&state=PR');

        $response->assertStatus(200);
        $this->assertEquals('MERCADO X', $response->json('data.0.issuer_name'));
    }
}
