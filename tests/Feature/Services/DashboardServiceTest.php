<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_top_products_shows_canonical_name_when_aliased(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();

        $invoiceA = Invoice::factory()->for($user)->for($issuerA)->create();
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);
        $invoiceB = Invoice::factory()->for($user)->for($issuerB)->create();
        InvoiceItem::factory()->for($invoiceB)->create(['description' => 'COCA-COLA LATA 350ML']);

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->getViewData($user->id);

        $topProducts = collect($result['topProducts']);
        $this->assertCount(1, $topProducts);
        $this->assertEquals('Coca-Cola 350ml', $topProducts->first()->description);
        $this->assertEquals(2, $topProducts->first()->frequency);
    }
}
