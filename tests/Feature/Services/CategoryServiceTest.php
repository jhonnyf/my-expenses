<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategoryService::class);
    }

    public function test_get_categories_returns_empty_for_user_without_categories(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getCategoriesWithSpending($user->id);

        $this->assertCount(0, $result);
    }

    public function test_get_categories_returns_user_categories(): void
    {
        $user = User::factory()->create();
        Category::factory()->count(3)->for($user)->create();
        Category::factory()->create(); // de outro usuário

        $result = $this->service->getCategoriesWithSpending($user->id);

        $this->assertCount(3, $result);
    }

    public function test_get_categories_includes_spending_totals(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);

        InvoiceItem::factory()->for($invoice)->create([
            'category_id' => $category->id,
            'total_price' => 50.00,
        ]);

        $result = $this->service->getCategoriesWithSpending($user->id);
        $found = $result->firstWhere('id', $category->id);

        $this->assertNotNull($found);
        $this->assertEquals(50.00, (float) $found->total_spent);
    }

    public function test_get_categories_orders_by_total_spent_descending(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);

        $low = Category::factory()->for($user)->create(['name' => 'Baixo']);
        $high = Category::factory()->for($user)->create(['name' => 'Alto']);

        InvoiceItem::factory()->for($invoice)->create(['category_id' => $low->id, 'total_price' => 10.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $high->id, 'total_price' => 90.00]);

        $result = $this->service->getCategoriesWithSpending($user->id);

        $this->assertSame($high->id, $result->first()->id);
        $this->assertSame($low->id, $result->last()->id);
    }

    public function test_count_uncategorized_items_returns_only_current_user_items_without_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        $otherInvoice = Invoice::factory()->for($other)->for($issuer)->create(['issued_at' => now()]);

        InvoiceItem::factory()->for($invoice)->create(['category_id' => null]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => null]);
        InvoiceItem::factory()->for($otherInvoice)->create(['category_id' => null]);

        $count = $this->service->countUncategorizedItems($user->id);

        $this->assertEquals(2, $count);
    }

    public function test_get_categories_defaults_to_current_month_spending(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();

        $thisMonthInvoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($thisMonthInvoice)->create(['category_id' => $category->id, 'total_price' => 50.00]);

        $lastMonthInvoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subMonth()]);
        InvoiceItem::factory()->for($lastMonthInvoice)->create(['category_id' => $category->id, 'total_price' => 100.00]);

        $result = $this->service->getCategoriesWithSpending($user->id);

        $this->assertEquals(50.00, (float) $result->firstWhere('id', $category->id)->total_spent);
    }

    public function test_get_categories_filters_by_custom_date_range(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(5)]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 30.00]);

        $oldInvoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subDays(40)]);
        InvoiceItem::factory()->for($oldInvoice)->create(['category_id' => $category->id, 'total_price' => 70.00]);

        $result = $this->service->getCategoriesWithSpending(
            $user->id,
            now()->subDays(10)->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        $this->assertEquals(30.00, (float) $result->firstWhere('id', $category->id)->total_spent);
    }

    public function test_count_uncategorized_items_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        $oldInvoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()->subMonth()]);

        InvoiceItem::factory()->for($invoice)->create(['category_id' => null]);
        InvoiceItem::factory()->for($oldInvoice)->create(['category_id' => null]);

        $count = $this->service->countUncategorizedItems($user->id);

        $this->assertEquals(1, $count);
    }

    public function test_auto_categorize_matches_items_by_keyword(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create(['keywords' => ['leite', 'integral']]);
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();
        $item = InvoiceItem::factory()->for($invoice)->create([
            'description' => 'LEITE INTEGRAL 1L',
            'category_id' => null,
        ]);

        $this->service->autoCategorize($user->id);

        $this->assertDatabaseHas('invoices_items', ['id' => $item->id, 'category_id' => $category->id]);
    }

    public function test_auto_categorize_returns_count_of_categorized_items(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create(['keywords' => ['arroz']]);
        $invoice = Invoice::factory()->for($user)->for($issuer)->create();

        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ TIPO 1', 'category_id' => null]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ PARBOILIZADO', 'category_id' => null]);

        $count = $this->service->autoCategorize($user->id);

        $this->assertEquals(2, $count);
    }
}
