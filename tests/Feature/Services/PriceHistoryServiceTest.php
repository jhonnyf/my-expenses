<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Services\PriceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PriceHistoryService::class);
    }

    public function test_search_returns_empty_when_no_products_match(): void
    {
        $user = User::factory()->create();

        $result = $this->service->search('produto inexistente', $user->id);

        $this->assertCount(0, $result);
    }

    public function test_search_returns_products_with_prices(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'CAFE MOIDO 500G', 'unit_price' => 12.90]);

        $result = $this->service->search('CAFE', $user->id);

        $this->assertNotEmpty($result);
    }

    public function test_timeline_returns_empty_for_unknown_product(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getTimeline('produto que nao existe', $user->id);

        $this->assertEmpty($result['timeline']);
        $this->assertEquals(0, $result['summary']['min_price']);
    }

    public function test_timeline_returns_price_points_for_product(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'OLEO DE SOJA 900ML',
            'unit_price'  => 7.50,
        ]);

        $result = $this->service->getTimeline('OLEO DE SOJA 900ML', $user->id);

        $this->assertNotEmpty($result);
    }

    public function test_search_isolates_data_by_user(): void
    {
        $userA   = User::factory()->create();
        $userB   = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($userB)->for($issuer)->create();
        InvoiceItem::factory()->for($invoice)->create(['description' => 'SAL REFINADO 1KG']);

        $result = $this->service->search('SAL', $userA->id);

        $this->assertCount(0, $result);
    }
}
