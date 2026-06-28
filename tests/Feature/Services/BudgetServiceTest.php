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
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget   = Budget::factory()->for($user)->for($category)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 30.00]);

        $result = $this->service->getBudgetsWithSpending($user->id);
        $found  = collect($result['budgets'])->firstWhere('id', $budget->id);

        $this->assertNotNull($found);
        $this->assertEquals(30.00, (float) $found->spent);
    }

    public function test_get_budgets_calculates_percentage_correctly(): void
    {
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget   = Budget::factory()->for($user)->for($category)->create(['amount' => 200.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id, 'total_price' => 50.00]);

        $result = $this->service->getBudgetsWithSpending($user->id);
        $found  = collect($result['budgets'])->firstWhere('id', $budget->id);

        $this->assertEquals(25.0, (float) $found->percentage);
        $this->assertEquals(150.00, (float) $found->remaining);
    }
}
