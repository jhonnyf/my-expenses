<?php

namespace Tests\Feature\Api\V1;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/budgets')->assertStatus(401);
    }

    public function test_index_returns_budgets_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Budget::factory()->count(2)->for($user)->create();
        Budget::factory()->create(); // de outro usuário

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/budgets')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['budgets', 'categories']]);
    }

    public function test_store_creates_budget(): void
    {
        $user     = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/budgets', [
                'category_id' => $category->id,
                'amount'      => 500.00,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.amount', '500.00');

        $this->assertDatabaseHas('budgets', [
            'user_id'     => $user->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_destroy_returns_401_when_unauthenticated(): void
    {
        $budget = Budget::factory()->create();

        $this->deleteJson("/api/v1/budgets/{$budget->id}")->assertStatus(401);
    }

    public function test_destroy_returns_403_when_budget_belongs_to_another_user(): void
    {
        $budget = Budget::factory()->create();
        $other  = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/budgets/{$budget->id}")
            ->assertStatus(403);
    }

    public function test_destroy_deletes_own_budget(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/budgets/{$budget->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }
}
