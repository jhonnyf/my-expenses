<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
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
        $user   = User::factory()->create();
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
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays($i * 10)]);
            InvoiceItem::factory()->for($invoice)->create(['description' => 'PRODUTO RECORRENTE']);
        }

        $result = $this->service->getRecurringItems($user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('PRODUTO RECORRENTE', $result->first()->description);
    }

    public function test_get_best_issuers_returns_empty_for_empty_descriptions(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getBestIssuers($user->id, collect());

        $this->assertCount(0, $result);
    }

    public function test_get_best_issuers_returns_cheapest_for_description(): void
    {
        $user    = User::factory()->create();
        $issuer1 = Issuer::factory()->create(['name' => 'CARO MERCADO']);
        $issuer2 = Issuer::factory()->create(['name' => 'BARATO MERCADO']);

        $expensiveInvoice = Invoice::factory()->for($user)->for($issuer1)->create();
        $cheapInvoice     = Invoice::factory()->for($user)->for($issuer2)->create();

        InvoiceItem::factory()->for($expensiveInvoice)->create(['description' => 'LEITE INTEGRAL', 'unit_price' => 8.00]);
        InvoiceItem::factory()->for($cheapInvoice)->create(['description' => 'LEITE INTEGRAL', 'unit_price' => 4.50]);

        $result = $this->service->getBestIssuers($user->id, collect(['LEITE INTEGRAL']));

        $this->assertCount(1, $result);
        $best = $result->first();
        $this->assertEquals('BARATO MERCADO', $best->issuer_name);
    }
}
