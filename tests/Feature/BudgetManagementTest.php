<?php

namespace Tests\Feature;

use App\Actions\NotifyBudgetThresholdsAction;
use App\Events\InvoiceImported;
use App\Listeners\CheckBudgetThresholdsListener;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Regras da página de Orçamentos: isolamento, totais sem contagem em dobro, mês navegável,
 * comparação, projeção, orçamento órfão, dashboard, alertas e plano gratuito.
 */
class BudgetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function invoiceOn(User $user, string $date, float $total = 100, array $items = []): Invoice
    {
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issued_at' => $date.' 10:00:00', 'total_amount' => $total]);

        foreach ($items as $categoryId => $price) {
            InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => $categoryId, 'total_price' => $price]);
        }

        return $invoice;
    }

    private function budgetFor(User $user, ?Category $category, float $amount): Budget
    {
        return Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category?->id, 'amount' => $amount]);
    }

    private function budgetsOf(User $user, string $query = ''): Collection
    {
        return $this->actingAs($user)->get('/budgets'.$query)->assertOk()->viewData('budgets');
    }

    // ─── Segurança ───────────────────────────────────────────────────────────────────────────

    public function test_store_rejects_category_of_another_user(): void
    {
        $user = User::factory()->pro()->create();
        $foreign = Category::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'Segredo']);

        $this->actingAs($user)->postJson('/budgets', ['category_id' => $foreign->id, 'amount' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/budgets', ['category_id' => $foreign->id, 'amount' => 100])
            ->assertStatus(422);

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_store_accepts_own_and_system_categories_for_pro_users(): void
    {
        $user = User::factory()->pro()->create();
        $own = Category::factory()->create(['user_id' => $user->id]);
        $system = Category::factory()->create(['user_id' => null]);

        $this->actingAs($user)->postJson('/budgets', ['category_id' => $own->id, 'amount' => 100])->assertOk();
        $this->actingAs($user)->postJson('/budgets', ['category_id' => $system->id, 'amount' => 100])->assertOk();
    }

    public function test_store_limits_amount_and_free_ajax_gets_json_402_instead_of_a_redirect(): void
    {
        $free = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $free->id]);

        $this->actingAs(User::factory()->create())->postJson('/budgets', ['amount' => 100000000])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($free)->postJson('/budgets', ['category_id' => $category->id, 'amount' => 100])
            ->assertStatus(402)->assertJsonPath('upgrade_required', true);
    }

    public function test_destroy_returns_404_for_budget_of_another_user(): void
    {
        $budget = $this->budgetFor(User::factory()->create(), null, 100);

        $this->actingAs(User::factory()->create())->deleteJson("/budgets/{$budget->id}")->assertNotFound();

        $this->assertModelExists($budget);
    }

    // ─── Totais ───────────────────────────────────────────────────────────────────────────────

    public function test_summary_uses_only_the_general_budget_when_there_is_one(): void
    {
        $user = User::factory()->create();
        $food = Category::factory()->create(['user_id' => $user->id]);
        $this->budgetFor($user, null, 1000);
        $this->budgetFor($user, $food, 200);
        $this->invoiceOn($user, now()->toDateString(), 300, [$food->id => 250]);

        $this->actingAs($user)->get('/budgets')->assertViewHas('summary', fn ($summary) => $summary['scope'] === 'general'
            && $summary['total_budgeted'] === 1000.0
            && $summary['total_spent'] === 300.0
            && $summary['total_remaining'] === 700.0
            && $summary['over_budget_count'] === 1);
    }

    public function test_summary_sums_category_budgets_when_there_is_no_general_one(): void
    {
        $user = User::factory()->create();
        $a = Category::factory()->create(['user_id' => $user->id]);
        $b = Category::factory()->create(['user_id' => $user->id]);
        $this->budgetFor($user, $a, 100);
        $this->budgetFor($user, $b, 50);
        $this->invoiceOn($user, now()->toDateString(), 90, [$a->id => 40, $b->id => 20]);

        $this->actingAs($user)->get('/budgets')->assertViewHas('summary', fn ($summary) => $summary['scope'] === 'categories'
            && $summary['total_budgeted'] === 150.0 && $summary['total_spent'] === 60.0);
    }

    public function test_general_budget_spending_uses_invoice_totals_net_of_discount(): void
    {
        $user = User::factory()->create();
        $this->budgetFor($user, null, 500);
        // itens somam 100, mas a nota foi paga por 90 (desconto)
        $invoice = $this->invoiceOn($user, now()->toDateString(), 90);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => null, 'total_price' => 100]);

        $this->assertSame(90.0, $this->budgetsOf($user)->first()->spent);
    }

    // ─── Mês: limites, navegação e comparação ────────────────────────────────────────────

    public function test_spending_is_bounded_to_the_month_and_navigable(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();
        $this->budgetFor($user, null, 1000);
        $this->invoiceOn($user, '2026-09-10', 100);
        $this->invoiceOn($user, '2026-10-05', 999); // data futura: fora do mês corrente
        $this->invoiceOn($user, '2026-08-20', 40);

        $this->assertSame(100.0, $this->budgetsOf($user)->first()->spent);

        $this->actingAs($user)->get('/budgets?month=2026-08')
            ->assertViewHas('budgets', fn ($budgets) => $budgets->first()->spent === 40.0 && $budgets->first()->projected === null)
            ->assertViewHas('month', fn ($month) => $month['value'] === '2026-08' && ! $month['is_current']
                && $month['previous'] === '2026-07' && $month['next'] === '2026-09');
    }

    public function test_month_must_not_be_in_the_future_or_malformed(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)->get('/budgets?month=2026-10')->assertSessionHasErrors('month');
        $this->actingAs($user)->get('/budgets?month=setembro')->assertSessionHasErrors('month');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/budgets?month=2026-10')->assertStatus(422);
    }

    public function test_budget_is_compared_with_the_previous_month(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();
        $food = Category::factory()->create(['user_id' => $user->id]);
        $new = Category::factory()->create(['user_id' => $user->id]);
        $this->budgetFor($user, $food, 500);
        $this->budgetFor($user, $new, 500);
        $this->invoiceOn($user, '2026-09-05', 150, [$food->id => 150, $new->id => 30]);
        $this->invoiceOn($user, '2026-08-05', 100, [$food->id => 100]);

        $budgets = $this->budgetsOf($user);

        $this->assertSame(50.0, $budgets->firstWhere('category_id', $food->id)->delta_pct);
        $this->assertSame(100.0, $budgets->firstWhere('category_id', $food->id)->previous_spent);
        $this->assertNull($budgets->firstWhere('category_id', $new->id)->delta_pct);
    }

    // ─── Projeção ────────────────────────────────────────────────────────────────────────────

    public function test_projection_estimates_month_end_and_the_day_the_limit_is_hit(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00'); // setembro tem 30 dias
        $user = User::factory()->create();
        $this->budgetFor($user, null, 200);
        $this->invoiceOn($user, '2026-09-05', 100);

        $budget = $this->budgetsOf($user)->first();

        $this->assertSame(300.0, $budget->projected);           // 100/10 dias × 30
        $this->assertSame(150.0, $budget->projected_percentage);
        $this->assertSame('2026-09-20', $budget->exceeds_on);    // 200 ÷ 10/dia
        $this->assertSame(4.76, $budget->daily_available);       // 100 restantes ÷ 21 dias (com hoje)
    }

    public function test_projection_is_skipped_early_in_the_month_or_when_already_exceeded(): void
    {
        $user = User::factory()->create();
        $this->budgetFor($user, null, 200);

        Carbon::setTestNow('2026-09-02 12:00:00');
        $this->invoiceOn($user, '2026-09-01', 50);
        $early = $this->budgetsOf($user)->first();
        $this->assertNull($early->projected);
        $this->assertNotNull($early->daily_available);

        Carbon::setTestNow('2026-09-20 12:00:00');
        $this->invoiceOn($user, '2026-09-19', 400);
        $exceeded = $this->budgetsOf($user)->first();
        $this->assertNull($exceeded->exceeds_on);
        $this->assertNull($exceeded->daily_available);
    }

    public function test_every_card_shows_a_projection_message(): void
    {
        $user = User::factory()->pro()->create();
        $a = Category::factory()->create(['user_id' => $user->id]);
        $b = Category::factory()->create(['user_id' => $user->id]);
        $c = Category::factory()->create(['user_id' => $user->id]);
        $d = Category::factory()->create(['user_id' => $user->id]);
        foreach ([$a, $b, $c, $d] as $category) {
            $this->budgetFor($user, $category, 200);
        }

        Carbon::setTestNow('2026-09-10 12:00:00'); // 10 dias de 30
        $this->invoiceOn($user, '2026-09-05', 300, [$a->id => 100, $b->id => 40, $c->id => 250]);

        $this->actingAs($user)->get('/budgets')->assertOk()
            ->assertSee('o limite estoura por volta de 20/09')      // a: 100 em 10 dias → passa de 200
            ->assertSee('o mês fecha em R$ 120,00 (60% do limite)') // b: 40 em 10 dias → 120
            ->assertSee('Orçamento excedido em R$ 50,00')            // c: 250 de 200
            ->assertSee('Ainda sem gastos neste mês.');              // d

        $this->actingAs($user)->get('/budgets?month=2026-08')->assertOk()
            ->assertSee('Sem gastos neste mês.')
            ->assertDontSee('Ainda sem gastos');

        Carbon::setTestNow('2026-09-30 12:00:00');
        $this->actingAs($user)->get('/budgets?month=2026-09')->assertOk()
            ->assertSee('Orçamento excedido em R$ 50,00');
    }

    public function test_projection_message_for_early_month_and_closed_month(): void
    {
        $user = User::factory()->create();
        $this->budgetFor($user, null, 500);

        Carbon::setTestNow('2026-09-02 12:00:00');
        $this->invoiceOn($user, '2026-09-01', 50);
        $this->actingAs($user)->get('/budgets')->assertOk()->assertSee('Poucos dias de dados para projetar o mês.');

        $this->invoiceOn($user, '2026-08-10', 200);
        $this->actingAs($user)->get('/budgets?month=2026-08')->assertOk()->assertSee('Mês fechado em R$ 200,00 (40% do limite).');
    }

    // ─── Orçamento órfão ─────────────────────────────────────────────────────────────────────

    public function test_deleting_a_category_deletes_its_budget_instead_of_turning_it_into_a_general_one(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $other = Category::factory()->create(['user_id' => $user->id]);
        $this->budgetFor($user, $category, 100);
        $this->budgetFor($user, $other, 100);

        $this->actingAs($user)->deleteJson("/categories/{$category->id}")->assertOk();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/categories/{$other->id}")->assertOk();

        $this->assertDatabaseCount('budgets', 0);
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────────────────────

    public function test_dashboard_compares_budgets_with_the_current_month_whatever_the_period(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();
        $this->budgetFor($user, null, 1000);
        $this->invoiceOn($user, '2026-09-05', 100);
        $this->invoiceOn($user, '2026-03-05', 500);

        $this->actingAs($user)->get('/dashboard?start_date=2026-01-01&end_date=2026-09-15')
            ->assertOk()
            ->assertViewHas('budgets', fn ($budgets) => $budgets->first()->spent === 100.0);
    }

    // ─── Plano gratuito ──────────────────────────────────────────────────────────────────────

    public function test_page_tells_free_users_that_category_budgets_are_pro(): void
    {
        Category::factory()->create(['user_id' => null, 'name' => 'Mercado']);

        $this->actingAs(User::factory()->create())->get('/budgets')->assertOk()
            ->assertSee('Mercado (Pro)')->assertSee('plano Pro');

        $this->actingAs(User::factory()->pro()->create())->get('/budgets')->assertOk()
            ->assertDontSee('(Pro)')->assertDontSee('No plano gratuito');
    }

    public function test_past_months_are_read_only(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();
        $this->budgetFor($user, null, 500);

        $this->actingAs($user)->get('/budgets?month=2026-08')->assertOk()
            ->assertSee('Os limites valem para todos os meses')
            ->assertDontSee('data-action="save-budget"', false)
            ->assertDontSee('data-action="edit-budget"', false);
    }

    // ─── API ─────────────────────────────────────────────────────────────────────────────────

    public function test_api_index_exposes_summary_month_projection_and_comparison_keys(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');
        $user = User::factory()->create();
        $this->budgetFor($user, null, 200);
        $this->invoiceOn($user, '2026-09-05', 100);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/budgets')->assertOk();

        $response->assertJsonPath('data.summary.scope', 'general')
            ->assertJsonPath('data.summary.total_spent', 100)
            ->assertJsonPath('data.month.value', '2026-09')
            ->assertJsonPath('data.month.is_current', true)
            ->assertJsonPath('data.budgets.0.projected', 300)
            ->assertJsonPath('data.budgets.0.exceeds_on', '2026-09-20');
        $this->assertArrayHasKey('delta_pct', $response->json('data.budgets.0'));
        $this->assertNull($response->json('data.budgets.0.delta_pct'));

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/budgets?month=2026-08')
            ->assertJsonPath('data.month.is_current', false)
            ->assertJsonPath('data.budgets.0.spent', 0);
    }

    public function test_api_store_returns_the_budget_with_current_spending(): void
    {
        $user = User::factory()->create();
        $this->invoiceOn($user, now()->toDateString(), 80);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/budgets', ['amount' => 400])
            ->assertCreated()
            ->assertJsonPath('data.spent', 80)
            ->assertJsonPath('data.remaining', 320);
    }

    // ─── Alertas de 80% e 100% ─────────────────────────────────────────────────────────────

    public function test_alerts_once_per_threshold_and_month(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id, 'name' => 'Mercado']);
        $budget = $this->budgetFor($user, $category, 100);
        $action = app(NotifyBudgetThresholdsAction::class);

        $this->assertSame(0, $action->execute($user));                       // 0%
        $invoice = $this->invoiceOn($user, '2026-09-05', 70, [$category->id => 70]);
        $this->assertSame(0, $action->execute($user));                       // 70%

        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => $category->id, 'total_price' => 15]);
        $this->assertSame(1, $action->execute($user));                       // 85% → aviso de 80
        $this->assertSame(0, $action->execute($user));                       // sem reavisar

        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'category_id' => $category->id, 'total_price' => 20]);
        $this->assertSame(1, $action->execute($user));                       // 105% → aviso de 100
        $this->assertSame(0, $action->execute($user));

        Notification::assertSentToTimes($user, BudgetThresholdReached::class, 2);
        Notification::assertSentTo($user, BudgetThresholdReached::class, function ($notification) use ($user) {
            $data = $notification->toArray($user);

            return $data['category_name'] === 'Mercado' && $data['level'] === 100 && $data['spent'] === 105.0 && $data['amount'] === 100.0;
        });
        $this->assertSame(100, $budget->fresh()->alerted_level);
        $this->assertSame('2026-09', $budget->fresh()->alerted_month);
    }

    public function test_alerts_start_over_in_a_new_month(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->budgetFor($user, null, 100);
        $action = app(NotifyBudgetThresholdsAction::class);

        Carbon::setTestNow('2026-09-15 12:00:00');
        $this->invoiceOn($user, '2026-09-05', 120);
        $this->assertSame(1, $action->execute($user));

        Carbon::setTestNow('2026-10-15 12:00:00');
        $this->invoiceOn($user, '2026-10-05', 90);
        $this->assertSame(1, $action->execute($user)); // outubro: 90% → novo aviso de 80
    }

    public function test_alert_runs_when_an_invoice_is_imported(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-09-15 12:00:00');
        $user = User::factory()->create();
        $this->budgetFor($user, null, 100);
        $invoice = $this->invoiceOn($user, '2026-09-05', 150);

        app(CheckBudgetThresholdsListener::class)->handle(new InvoiceImported($invoice));

        Notification::assertSentTo($user, BudgetThresholdReached::class);
    }
}
