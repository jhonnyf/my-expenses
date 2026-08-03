<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Services\PriceComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PriceComparisonService::class);
    }

    public function test_search_products_finds_candidates_by_fuzzy_term(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);

        $result = $this->service->searchProducts('ARROZ', $user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('ARROZ BRANCO 5KG', $result->first()->name);
    }

    public function test_search_products_does_not_mix_different_matching_products(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ INTEGRAL 1KG']);

        $result = $this->service->searchProducts('ARROZ', $user->id);

        $this->assertCount(2, $result);
    }

    public function test_by_city_ranks_cheapest_city_first(): void
    {
        $user = User::factory()->create();
        $curitiba = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($curitiba)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 25.00]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($saoPaulo)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 18.00]);

        $result = $this->service->byCity('ARROZ BRANCO 5KG', $user->id);

        $this->assertCount(2, $result);
        $this->assertEquals('São Paulo', $result->first()->city);
        $this->assertEqualsWithDelta(18.00, $result->first()->min_price, 0.001);
    }

    public function test_by_city_does_not_mix_similar_products(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ INTEGRAL 1KG', 'unit_price' => 8.00]);

        $result = $this->service->byCity('ARROZ BRANCO 5KG', $user->id);

        $this->assertCount(1, $result);
        $this->assertEqualsWithDelta(20.00, $result->first()->min_price, 0.001);
    }

    public function test_by_city_aggregates_across_all_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'FEIJAO PRETO 1KG', 'unit_price' => 8.00]);
        InvoiceItem::factory()->for(Invoice::factory()->for($other)->for($issuer)->create())
            ->create(['description' => 'FEIJAO PRETO 1KG', 'unit_price' => 6.00]);

        $result = $this->service->byCity('FEIJAO PRETO 1KG', $user->id);

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result->first()->sample_count);
        $this->assertEqualsWithDelta(6.00, $result->first()->min_price, 0.001);
    }

    public function test_by_city_excludes_issuers_without_city(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => null, 'state' => null]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'LEITE INTEGRAL 1L']);

        $result = $this->service->byCity('LEITE INTEGRAL 1L', $user->id);

        $this->assertCount(0, $result);
    }

    public function test_by_issuer_ranks_cheapest_issuer_within_city(): void
    {
        $user = User::factory()->create();
        $cheap = Issuer::factory()->create(['name' => 'MERCADO BARATO', 'city' => 'Curitiba', 'state' => 'PR']);
        $expensive = Issuer::factory()->create(['name' => 'MERCADO CARO', 'city' => 'Curitiba', 'state' => 'PR']);
        $otherCity = Issuer::factory()->create(['name' => 'MERCADO DISTANTE', 'city' => 'São Paulo', 'state' => 'SP']);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($cheap)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 15.00]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($expensive)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 25.00]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($otherCity)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 5.00]);

        $result = $this->service->byIssuer('ARROZ BRANCO 5KG', 'Curitiba', 'PR', $user->id);

        $this->assertCount(2, $result);
        $this->assertEquals('MERCADO BARATO', $result->first()->issuer_name);
    }

    public function test_cheapest_offer_does_not_mix_similar_products(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);

        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 20.00]);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ INTEGRAL 1KG', 'unit_price' => 8.00]);

        $offer = $this->service->cheapestOffer('ARROZ BRANCO 5KG', $user->id);

        $this->assertEqualsWithDelta(20.00, $offer['price'], 0.001);
    }
}
