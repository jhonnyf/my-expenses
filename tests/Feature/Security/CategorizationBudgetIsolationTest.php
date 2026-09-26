<?php

namespace Tests\Feature\Security;

use App\Actions\NotifyBudgetThresholdsAction;
use App\Enums\SubscriptionPlan;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ItemCategoryRule;
use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Categorização e orçamentos: o que A faz (ou vê) nunca toca nos itens, categorias, regras,
 * orçamentos ou avisos de B — na web e na API v1.
 */
class CategorizationBudgetIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->pro()->create();
        $this->victim = User::factory()->pro()->create();
    }

    private function itemOf(User $user, string $description, array $invoice = []): InvoiceItem
    {
        return InvoiceItem::factory()
            ->for(Invoice::factory()->for($user)->create(['issued_at' => now()] + $invoice))
            ->create(['description' => $description, 'category_id' => null]);
    }

    // ─── categorias na API ───────────────────────────────────────────────────

    public function test_api_categories_are_isolated(): void
    {
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id, 'name' => 'CATEGORIA SECRETA']);
        $own = Category::factory()->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker, 'sanctum');

        $this->getJson('/api/v1/categories')->assertOk()->assertDontSee('CATEGORIA SECRETA');
        $this->getJson("/api/v1/categories/{$victimCategory->id}")->assertNotFound();
        $this->putJson("/api/v1/categories/{$victimCategory->id}", ['name' => 'Hack', 'color' => '#000000'])->assertNotFound();
        $this->deleteJson("/api/v1/categories/{$victimCategory->id}")->assertNotFound();
        $this->postJson("/api/v1/categories/{$victimCategory->id}/merge", ['target_id' => $own->id])->assertNotFound();
        $this->postJson("/api/v1/categories/{$own->id}/merge", ['target_id' => $victimCategory->id])->assertUnprocessable();

        $this->assertSame('CATEGORIA SECRETA', $victimCategory->fresh()->name);
    }

    public function test_api_assign_item_rejects_foreign_item_and_foreign_category(): void
    {
        $victimItem = $this->itemOf($this->victim, 'LEITE');
        $ownItem = $this->itemOf($this->attacker, 'PAO');
        $ownCategory = Category::factory()->create(['user_id' => $this->attacker->id]);
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker, 'sanctum');

        $this->postJson('/api/v1/categories/assign-item', ['item_id' => $victimItem->id, 'category_id' => $ownCategory->id])->assertNotFound();
        $this->postJson('/api/v1/categories/assign-item', ['item_id' => $ownItem->id, 'category_id' => $victimCategory->id])->assertUnprocessable();

        $this->assertNull($victimItem->fresh()->category_id);
        $this->assertNull($ownItem->fresh()->category_id);
    }

    // ─── listagens ───────────────────────────────────────────────────────────

    public function test_category_pages_never_show_another_users_data(): void
    {
        Category::factory()->create(['user_id' => $this->victim->id, 'name' => 'CATEGORIA SECRETA']);
        $this->itemOf($this->victim, 'PRODUTO SIGILOSO');

        $this->actingAs($this->attacker);

        $this->get(route('categories.index'))->assertOk()->assertDontSee('CATEGORIA SECRETA');
        $this->get(route('categories.uncategorized', ['start_date' => '2000-01-01']))->assertOk()->assertDontSee('PRODUTO SIGILOSO');
        $this->getJson('/api/v1/categories/uncategorized?start_date=2000-01-01')->assertOk()->assertDontSee('PRODUTO SIGILOSO');
    }

    public function test_system_category_detail_only_counts_own_spending(): void
    {
        $system = Category::factory()->create(['user_id' => null, 'name' => 'Sistema']);
        InvoiceItem::factory()
            ->for(Invoice::factory()->for($this->victim)->create(['issued_at' => now()]))
            ->create(['category_id' => $system->id, 'description' => 'ITEM DA VITIMA', 'total_price' => 500]);

        $this->actingAs($this->attacker, 'sanctum')
            ->getJson("/api/v1/categories/{$system->id}")
            ->assertOk()
            ->assertDontSee('ITEM DA VITIMA');
    }

    public function test_keyword_preview_only_counts_own_items(): void
    {
        $this->itemOf($this->victim, 'ZZQUEIJO MINAS');

        $this->actingAs($this->attacker)
            ->postJson(route('categories.preview-keywords'), ['keywords' => 'ZZQUEIJO'])
            ->assertOk()
            ->assertJson(['count' => 0, 'samples' => []]);

        $this->actingAs($this->attacker, 'sanctum')
            ->postJson('/api/v1/categories/preview-keywords', ['keywords' => 'ZZQUEIJO'])
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    // ─── auto-categorização, reversão, IA, regras ───────────────────────────

    public function test_auto_categorize_and_revert_only_touch_own_items(): void
    {
        Queue::fake();
        $category = Category::factory()->create(['user_id' => $this->attacker->id, 'keywords' => ['ZZQUEIJO']]);
        $ownItem = $this->itemOf($this->attacker, 'ZZQUEIJO PRATO');
        $victimItem = $this->itemOf($this->victim, 'ZZQUEIJO MINAS');
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id]);
        $victimAuto = $this->itemOf($this->victim, 'OUTRO PRODUTO');
        $victimAuto->update(['category_id' => $victimCategory->id, 'categorization_source' => InvoiceItem::SOURCE_KEYWORD]);

        $this->actingAs($this->attacker)->postJson(route('categories.auto-categorize'))->assertOk();

        $this->assertSame($category->id, $ownItem->fresh()->category_id);
        $this->assertNull($victimItem->fresh()->category_id);

        $this->actingAs($this->attacker)->postJson(route('categories.revert-auto-categorization'))->assertOk();

        $this->assertNull($ownItem->fresh()->category_id);
        $this->assertSame($victimCategory->id, $victimAuto->fresh()->category_id);
        $this->assertSame(InvoiceItem::SOURCE_KEYWORD, $victimAuto->fresh()->categorization_source);
    }

    public function test_ai_item_suggestion_rejects_another_users_item(): void
    {
        $victimItem = $this->itemOf($this->victim, 'LEITE');

        $this->actingAs($this->attacker)
            ->postJson(route('categories.suggest-item-category'), ['item_id' => $victimItem->id])
            ->assertNotFound();

        $this->actingAs($this->attacker, 'sanctum')
            ->postJson('/api/v1/categories/suggest-item-category', ['item_id' => $victimItem->id])
            ->assertNotFound();
    }

    public function test_learned_rule_belongs_to_the_user_who_categorized(): void
    {
        $ownItem = $this->itemOf($this->attacker, 'CAFE PILAO 500G');
        $victimItem = $this->itemOf($this->victim, 'CAFE PILAO 500G');
        $category = Category::factory()->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)
            ->postJson(route('categories.assign-item'), ['item_id' => $ownItem->id, 'category_id' => $category->id])
            ->assertOk();

        $this->assertSame([$this->attacker->id], ItemCategoryRule::pluck('user_id')->unique()->values()->all());
        $this->assertNull($victimItem->fresh()->category_id);

        // A regra do atacante não categoriza item novo da vítima.
        Queue::fake();
        $this->actingAs($this->victim)->postJson(route('categories.auto-categorize'))->assertOk();
        $this->assertNull($victimItem->fresh()->category_id);
    }

    // ─── orçamentos ──────────────────────────────────────────────────────────

    public function test_budget_lists_and_spending_are_own_only(): void
    {
        Budget::factory()->create(['user_id' => $this->victim->id, 'category_id' => null, 'amount' => 777]);
        $own = Budget::factory()->create(['user_id' => $this->attacker->id, 'category_id' => null, 'amount' => 100]);
        Invoice::factory()->for($this->victim)->create(['issued_at' => now(), 'total_amount' => 5000]);

        $data = $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/budgets')->assertOk()->json('data');

        $this->assertCount(1, $data['budgets']);
        $this->assertSame($own->id, $data['budgets'][0]['id']);
        $this->assertEquals(0, $data['budgets'][0]['spent']);

        $this->actingAs($this->attacker)->get(route('budgets.index'))->assertOk()->assertDontSee('777,00');
    }

    public function test_storing_a_budget_never_overwrites_another_users_budget(): void
    {
        $victimBudget = Budget::factory()->create(['user_id' => $this->victim->id, 'category_id' => null, 'amount' => 777]);

        $this->actingAs($this->attacker)->postJson(route('budgets.store'), ['amount' => 10])->assertOk();
        $this->actingAs($this->attacker, 'sanctum')->postJson('/api/v1/budgets', ['amount' => 11])->assertSuccessful();

        $this->assertEquals(777, $victimBudget->fresh()->amount);
        $this->assertSame(1, Budget::where('user_id', $this->attacker->id)->count());
    }

    public function test_api_budget_rejects_foreign_category_and_free_plan_category(): void
    {
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id]);
        $free = User::factory()->create();
        $freeCategory = Category::factory()->create(['user_id' => $free->id]);
        $this->assertSame(SubscriptionPlan::Free, $free->subscription->plan);

        $this->actingAs($this->attacker, 'sanctum')
            ->postJson('/api/v1/budgets', ['category_id' => $victimCategory->id, 'amount' => 10])
            ->assertUnprocessable();

        $this->actingAs($free, 'sanctum')
            ->postJson('/api/v1/budgets', ['category_id' => $freeCategory->id, 'amount' => 10])
            ->assertStatus(402);
    }

    public function test_budget_alerts_go_only_to_the_budget_owner(): void
    {
        Notification::fake();
        Budget::factory()->create(['user_id' => $this->victim->id, 'category_id' => null, 'amount' => 10]);
        Invoice::factory()->for($this->victim)->create(['issued_at' => now(), 'total_amount' => 100]);
        // O atacante não tem orçamento: o gasto da vítima não pode gerar aviso para ele.
        Invoice::factory()->for($this->attacker)->create(['issued_at' => now(), 'total_amount' => 100]);

        app(NotifyBudgetThresholdsAction::class)->execute($this->victim);
        app(NotifyBudgetThresholdsAction::class)->execute($this->attacker);

        Notification::assertSentTo($this->victim, BudgetThresholdReached::class);
        Notification::assertNotSentTo($this->attacker, BudgetThresholdReached::class);
    }
}
