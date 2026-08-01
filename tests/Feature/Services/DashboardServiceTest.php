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

        $invoiceA = Invoice::factory()->for($user)->for($issuerA)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoiceA)->create(['description' => 'REFRIG COCA COLA 350ML LAT']);
        $invoiceB = Invoice::factory()->for($user)->for($issuerB)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoiceB)->create(['description' => 'COCA-COLA LATA 350ML']);

        ProductAlias::create(['user_id' => $user->id, 'description' => 'REFRIG COCA COLA 350ML LAT', 'canonical_name' => 'Coca-Cola 350ml']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COCA-COLA LATA 350ML', 'canonical_name' => 'Coca-Cola 350ml']);

        $result = $this->service->getViewData($user->id);

        $topProducts = collect($result['topProducts']);
        $this->assertCount(1, $topProducts);
        $this->assertEquals('Coca-Cola 350ml', $topProducts->first()->description);
        $this->assertEquals(2, $topProducts->first()->frequency);
    }

    public function test_default_period_is_current_month_to_date(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now(), 'total_amount' => 50.00]);
        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subMonth(), 'total_amount' => 100.00]);

        $result = $this->service->getViewData($user->id);

        $this->assertEquals(50.00, $result['totalExpenses']);
        $this->assertEquals(now()->startOfMonth()->format('Y-m-d'), $result['filters']['start_date']);
        $this->assertEquals(now()->format('Y-m-d'), $result['filters']['end_date']);
    }

    public function test_get_view_data_filters_by_custom_date_range(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(5), 'total_amount' => 30.00]);
        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(40), 'total_amount' => 70.00]);

        $result = $this->service->getViewData(
            $user->id,
            now()->subDays(10)->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        $this->assertEquals(30.00, $result['totalExpenses']);
    }

    public function test_get_view_data_compares_against_equal_length_previous_period(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        // Período selecionado: últimos 5 dias (inclusive) — período anterior deve ser os 5 dias antes disso.
        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(2), 'total_amount' => 40.00]);
        Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(7), 'total_amount' => 20.00]);

        $result = $this->service->getViewData(
            $user->id,
            now()->subDays(4)->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        $this->assertEquals(40.00, $result['periodExpenses']);
        $this->assertEquals(20.00, $result['previousPeriodExpenses']);
        $this->assertEquals(100.0, $result['periodVariation']);
    }
}
