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
}
