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
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create();
        $invoice  = Invoice::factory()->for($user)->for($issuer)->create();

        InvoiceItem::factory()->for($invoice)->create([
            'category_id' => $category->id,
            'total_price' => 50.00,
        ]);

        $result = $this->service->getCategoriesWithSpending($user->id);
        $found  = $result->firstWhere('id', $category->id);

        $this->assertNotNull($found);
        $this->assertEquals(50.00, (float) $found->total_spent);
    }

    public function test_auto_categorize_matches_items_by_keyword(): void
    {
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create(['keywords' => ['leite', 'integral']]);
        $invoice  = Invoice::factory()->for($user)->for($issuer)->create();
        $item     = InvoiceItem::factory()->for($invoice)->create([
            'description' => 'LEITE INTEGRAL 1L',
            'category_id' => null,
        ]);

        $this->service->autoCategorize($user->id);

        $this->assertDatabaseHas('invoices_items', ['id' => $item->id, 'category_id' => $category->id]);
    }

    public function test_auto_categorize_returns_count_of_categorized_items(): void
    {
        $user     = User::factory()->create();
        $issuer   = Issuer::factory()->create();
        $category = Category::factory()->for($user)->create(['keywords' => ['arroz']]);
        $invoice  = Invoice::factory()->for($user)->for($issuer)->create();

        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ TIPO 1', 'category_id' => null]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ PARBOILIZADO', 'category_id' => null]);

        $count = $this->service->autoCategorize($user->id);

        $this->assertEquals(2, $count);
    }
}
