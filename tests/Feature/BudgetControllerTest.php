<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/budgets')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/budgets')
            ->assertStatus(200);
    }

    public function test_index_returns_summary_and_ordered_budgets(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        $overCategory = Category::factory()->for($user)->create();
        $okCategory = Category::factory()->for($user)->create();
        $overBudget = Budget::factory()->for($user)->for($overCategory)->create(['amount' => 50.00]);
        Budget::factory()->for($user)->for($okCategory)->create(['amount' => 100.00]);

        $invoice = Invoice::factory()->for($user)->for($issuer)->create(['issued_at' => now()]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $overCategory->id, 'total_price' => 80.00]);
        InvoiceItem::factory()->for($invoice)->create(['category_id' => $okCategory->id, 'total_price' => 10.00]);

        $this->actingAs($user)
            ->get('/budgets')
            ->assertStatus(200)
            ->assertViewHas('summary', fn ($summary) => $summary['total_budgeted'] === 150.0
                && $summary['over_budget_count'] === 1)
            ->assertViewHas('budgets', fn ($budgets) => $budgets->first()->id === $overBudget->id);
    }

    public function test_store_without_category_works_for_free_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/budgets', ['amount' => 500.00])
            ->assertStatus(200);

        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category_id' => null]);
    }

    public function test_store_with_category_redirects_to_upgrade_for_free_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/budgets', ['category_id' => $category->id, 'amount' => 500.00])
            ->assertRedirect(route('subscription.upgrade'));

        $this->assertDatabaseMissing('budgets', ['user_id' => $user->id, 'category_id' => $category->id]);
    }

    public function test_store_with_category_works_for_pro_user(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson('/budgets', ['category_id' => $category->id, 'amount' => 500.00])
            ->assertStatus(200);

        $this->assertDatabaseHas('budgets', ['user_id' => $user->id, 'category_id' => $category->id]);
    }
}
