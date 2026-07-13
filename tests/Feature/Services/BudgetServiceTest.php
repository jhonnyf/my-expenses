<?php

namespace Tests\Feature\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BudgetService::class);
    }

    public function test_get_budgets_with_spending_returns_structure(): void
    {
        $user = User::factory()->create();

        $result = $this->service->getBudgetsWithSpending($user->id);

        $this->assertArrayHasKey('budgets', $result);
        $this->assertArrayHasKey('categories', $result);
    }

    public function test_get_budgets_calculates_spent_from_invoice_items(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 30.00]);

        $result = $this->service->getBudgetsWithSpending($user->id);
        $found = collect($result['budgets'])->firstWhere('id', $budget->id);

        $this->assertNotNull($found);
        $this->assertEquals(30.00, (float) $found->spent);
    }

    public function test_get_budgets_calculates_percentage_correctly(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create(['amount' => 200.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 50.00]);

        $result = $this->service->getBudgetsWithSpending($user->id);
        $found = collect($result['budgets'])->firstWhere('id', $budget->id);

        $this->assertEquals(25.0, (float) $found->percentage);
        $this->assertEquals(150.00, (float) $found->remaining);
    }

    public function test_attach_spending_calculates_spent_for_single_category_budget(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 40.00]);

        $result = $this->service->attachSpending($budget);

        $this->assertEquals(40.00, (float) $result->spent);
        $this->assertEquals(40.0, (float) $result->percentage);
        $this->assertEquals(60.00, (float) $result->remaining);
    }

    public function test_attach_spending_ignores_other_categories(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $otherCat = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->for($category)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $otherCat->id, 'total_price' => 40.00]);

        $result = $this->service->attachSpending($budget);

        $this->assertEquals(0.0, (float) $result->spent);
    }

    public function test_get_budgets_orders_by_percentage_descending(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $lowCategory = Category::factory()->for($user)->create();
        $highCategory = Category::factory()->for($user)->create();
        $lowBudget = Budget::factory()->for($user)->for($lowCategory)->create(['amount' => 200.00]);
        $highBudget = Budget::factory()->for($user)->for($highCategory)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $lowCategory->id, 'total_price' => 20.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $highCategory->id, 'total_price' => 90.00]);

        $result = $this->service->getBudgetsWithSpending($user->id);

        $this->assertSame($highBudget->id, $result['budgets']->first()->id);
        $this->assertSame($lowBudget->id, $result['budgets']->last()->id);
    }

    public function test_get_budgets_returns_summary_totals(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $overCategory = Category::factory()->for($user)->create();
        $okCategory = Category::factory()->for($user)->create();
        Budget::factory()->for($user)->for($overCategory)->create(['amount' => 50.00]);
        Budget::factory()->for($user)->for($okCategory)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $overCategory->id, 'total_price' => 80.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $okCategory->id, 'total_price' => 30.00]);

        $result = $this->service->getBudgetsWithSpending($user->id);

        $this->assertEquals(150.00, $result['summary']['total_budgeted']);
        $this->assertEquals(110.00, $result['summary']['total_spent']);
        $this->assertEquals(70.00, $result['summary']['total_remaining']);
        $this->assertEquals(1, $result['summary']['over_budget_count']);
    }

    public function test_attach_spending_sums_all_categories_for_general_budget(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->for($user)->create(['category_id' => null, 'amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 15.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => null, 'total_price' => 5.00]);

        $result = $this->service->attachSpending($budget);

        $this->assertEquals(20.00, (float) $result->spent);
    }
}
