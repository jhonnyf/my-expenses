<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private SearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SearchService::class);
    }

    public function test_search_returns_empty_arrays_when_no_results(): void
    {
        $user = User::factory()->create();

        $result = $this->service->search('xyz_inexistente', $user->id);

        $this->assertArrayHasKey('emissores', $result);
        $this->assertArrayHasKey('notas_fiscais', $result);
        $this->assertArrayHasKey('produtos', $result);
        $this->assertEmpty($result['emissores']);
        $this->assertEmpty($result['notas_fiscais']);
        $this->assertEmpty($result['produtos']);
    }

    public function test_search_finds_issuer_by_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'SUPERMERCADO BONS PRECOS']);
        Invoice::factory()->for($user)->for($issuer)->create();

        $result = $this->service->search('BONS PRECOS', $user->id);

        $this->assertNotEmpty($result['emissores']);
    }

    public function test_search_finds_products_by_description(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'FEIJAO CARIOCA 1KG']);

        $result = $this->service->search('FEIJAO', $user->id);

        $this->assertNotEmpty($result['produtos']);
    }

    public function test_search_finds_product_by_canonical_name(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->search('Coca-Cola', $user->id);

        $this->assertNotEmpty($result['produtos']);
        $this->assertEquals('Coca-Cola 350ml', $result['produtos']->first()['title']);
    }

    public function test_search_does_not_return_other_users_data(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'FARMACIA SAUDE']);
        Invoice::factory()->for($userB)->for($issuer)->create();

        $result = $this->service->search('FARMACIA', $userA->id);

        $this->assertEmpty($result['emissores']);
    }

    public function test_search_filters_issuers_by_city_state(): void
    {
        $user = User::factory()->create();
        $curitiba = Issuer::factory()->create(['name' => 'MERCADO CENTRAL', 'city' => 'Curitiba', 'state' => 'PR']);
        $saoPaulo = Issuer::factory()->create(['name' => 'MERCADO CENTRAL', 'city' => 'São Paulo', 'state' => 'SP']);
        Invoice::factory()->for($user)->for($curitiba)->create();
        Invoice::factory()->for($user)->for($saoPaulo)->create();

        $result = $this->service->search('MERCADO', $user->id, 'São Paulo', 'SP');

        $this->assertCount(1, $result['emissores']);
    }

    public function test_search_filters_products_by_city_state(): void
    {
        $user = User::factory()->create();
        $curitiba = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($curitiba)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($saoPaulo)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG']);

        $resultFiltered = $this->service->search('ARROZ', $user->id, 'São Paulo', 'SP');
        $resultUnfiltered = $this->service->search('ARROZ', $user->id);

        // Sem filtro, os dois itens (Curitiba + São Paulo) caem no mesmo nome
        // canônico e são contados juntos (2x); filtrando por São Paulo/SP, só 1.
        $this->assertStringContainsString('2x', $resultUnfiltered['produtos']->first()['subtitle']);
        $this->assertStringContainsString('1x', $resultFiltered['produtos']->first()['subtitle']);
    }
}
