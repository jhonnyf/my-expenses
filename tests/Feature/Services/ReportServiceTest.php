<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportService::class);
    }

    private function createInvoiceWithItem(User $user, Issuer $issuer, array $invoiceOverrides = [], float $itemPrice = 10.00, array $itemOverrides = []): InvoiceItem
    {
        $invoiceOverrides = array_merge(['issued_at' => now()], $invoiceOverrides);
        $invoice          = Invoice::factory()->for($user)->for($issuer)->create($invoiceOverrides);

        return InvoiceItem::factory()->for($invoice)->create(
            array_merge(['total_price' => $itemPrice], $itemOverrides)
        );
    }

    public function test_build_report_data_returns_all_required_keys(): void
    {
        $user = User::factory()->create();

        $result = $this->service->buildReportData($user->id, []);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('categoryBreakdown', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('issuers', $result);
        $this->assertArrayHasKey('categories', $result);
    }

    public function test_build_report_data_filters_by_date_range(): void
    {
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $this->createInvoiceWithItem($user, $issuer, ['issued_at' => now()->subDays(5)], 50.00);
        $this->createInvoiceWithItem($user, $issuer, ['issued_at' => now()->subDays(40)], 100.00);

        $result = $this->service->buildReportData($user->id, [
            'start_date' => now()->subDays(10)->format('Y-m-d'),
            'end_date'   => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(50.00, (float) $result['summary']->total_amount);
    }

    public function test_build_report_data_filters_by_issuer_id(): void
    {
        $user    = User::factory()->create();
        $issuer1 = Issuer::factory()->create();
        $issuer2 = Issuer::factory()->create();

        $this->createInvoiceWithItem($user, $issuer1, [], 30.00);
        $this->createInvoiceWithItem($user, $issuer2, [], 70.00);

        $result = $this->service->buildReportData($user->id, ['issuer_id' => $issuer1->id]);

        $this->assertEquals(30.00, (float) $result['summary']->total_amount);
    }

    public function test_build_report_data_filters_by_category_id(): void
    {
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $invoice  = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);

        $catItem   = InvoiceItem::factory()->for($invoice)->create(['total_price' => 20.00, 'category_id' => $category->id]);
        $noCatItem = InvoiceItem::factory()->for($invoice)->create(['total_price' => 80.00, 'category_id' => null]);

        $result = $this->service->buildReportData($user->id, ['category_id' => $category->id]);

        $this->assertEquals(20.00, (float) $result['summary']->total_amount);
    }

    public function test_build_report_data_groups_by_category(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();

        $this->createInvoiceWithItem($user, $issuer, ['issued_at' => now()], 25.00);

        $result = $this->service->buildReportData($user->id, []);

        $this->assertNotEmpty($result['categoryBreakdown']);
    }
}
