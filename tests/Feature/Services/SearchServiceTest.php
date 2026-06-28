<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
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
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'SUPERMERCADO BONS PRECOS']);
        Invoice::factory()->for($user)->for($issuer)->create();

        $result = $this->service->search('BONS PRECOS', $user->id);

        $this->assertNotEmpty($result['emissores']);
    }

    public function test_search_finds_products_by_description(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'FEIJAO CARIOCA 1KG']);

        $result = $this->service->search('FEIJAO', $user->id);

        $this->assertNotEmpty($result['produtos']);
    }

    public function test_search_does_not_return_other_users_data(): void
    {
        $userA  = User::factory()->create();
        $userB  = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'FARMACIA SAUDE']);
        Invoice::factory()->for($userB)->for($issuer)->create();

        $result = $this->service->search('FARMACIA', $userA->id);

        $this->assertEmpty($result['emissores']);
    }
}
