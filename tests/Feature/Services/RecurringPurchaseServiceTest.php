<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\RecurringPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecurringPurchaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecurringPurchaseService::class);
    }

    public function test_get_recurring_items_returns_empty_when_no_purchases(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getRecurringItems($user->id);

        $this->assertCount(0, $result);
    }

    public function test_get_recurring_items_returns_empty_when_fewer_than_3_purchases(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        for ($i = 0; $i < 2; $i++) {
            $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays($i * 7)]);
            InvoiceItem::factory()->for($invoice)->create(['description' => 'PRODUTO REPETIDO']);
        }

        $result = $this->service->getRecurringItems($user->id);

        $this->assertCount(0, $result);
    }

    public function test_get_recurring_items_returns_items_bought_at_least_3_times(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays($i * 10)]);
            InvoiceItem::factory()->for($invoice)->create(['description' => 'PRODUTO RECORRENTE']);
        }

        $result = $this->service->getRecurringItems($user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('PRODUTO RECORRENTE', $result->first()->description);
    }

    public function test_get_recurring_items_combines_aliased_descriptions_into_one_recurrence(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();

        $invoiceA1 = Invoice::factory()->for($user)->for($issuerA)->create(['issued_at' => now()->subDays(30)]);
        InvoiceItem::factory()->for($invoiceA1)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);
        $invoiceA2 = Invoice::factory()->for($user)->for($issuerA)->create(['issued_at' => now()->subDays(20)]);
        InvoiceItem::factory()->for($invoiceA2)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);
        $invoiceB1 = Invoice::factory()->for($user)->for($issuerB)->create(['issued_at' => now()->subDays(10)]);
        InvoiceItem::factory()->for($invoiceB1)->create(['description' => 'COCA-COLA LATA 350ML']);

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->getRecurringItems($user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Coca-Cola 350ml', $result->first()->description);
        $this->assertEquals(3, $result->first()->purchase_count);
    }

    public function test_get_best_issuers_matches_by_canonical_name(): void
    {
        $user = User::factory()->create();
        $issuer1 = Issuer::factory()->create(['name' => 'CARO MERCADO']);
        $issuer2 = Issuer::factory()->create(['name' => 'BARATO MERCADO']);

        $expensiveInvoice = Invoice::factory()->for($user)->for($issuer1)->create();
        $cheapInvoice = Invoice::factory()->for($user)->for($issuer2)->create();

        InvoiceItem::factory()->for($expensiveInvoice)->create(['description' => 'REFRIG COCA COLA 350ML LAT', 'unit_price' => 8.00]);
        InvoiceItem::factory()->for($cheapInvoice)->create(['description' => 'COCA-COLA LATA 350ML', 'unit_price' => 4.50]);

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->getBestIssuers($user->id, collect(['Coca-Cola 350ml']));

        $this->assertCount(1, $result);
        $this->assertEquals('BARATO MERCADO', $result->first()->issuer_name);
    }

    public function test_get_best_issuers_returns_empty_for_empty_descriptions(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getBestIssuers($user->id, collect());

        $this->assertCount(0, $result);
    }

    public function test_get_best_issuers_returns_cheapest_for_description(): void
    {
        $user = User::factory()->create();
        $issuer1 = Issuer::factory()->create(['name' => 'CARO MERCADO']);
        $issuer2 = Issuer::factory()->create(['name' => 'BARATO MERCADO']);

        $expensiveInvoice = Invoice::factory()->for($user)->for($issuer1)->create();
        $cheapInvoice = Invoice::factory()->for($user)->for($issuer2)->create();

        InvoiceItem::factory()->for($expensiveInvoice)->create(['description' => 'LEITE INTEGRAL', 'unit_price' => 8.00]);
        InvoiceItem::factory()->for($cheapInvoice)->create(['description' => 'LEITE INTEGRAL', 'unit_price' => 4.50]);

        $result = $this->service->getBestIssuers($user->id, collect(['LEITE INTEGRAL']));

        $this->assertCount(1, $result);
        $best = $result->first();
        $this->assertEquals('BARATO MERCADO', $best->issuer_name);
    }
}
