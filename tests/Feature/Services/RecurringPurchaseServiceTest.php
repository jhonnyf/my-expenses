<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\RecurringDismissal;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\RecurringPurchaseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecurringPurchaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecurringPurchaseService::class);
    }

    /** Compra o produto em cada "há N dias" informado. */
    private function buy(User $user, array $daysAgo, string $description = 'LEITE', float $price = 5.0, ?Issuer $issuer = null, array $itemAttributes = [], InvoiceStatus $status = InvoiceStatus::Authorized): void
    {
        $issuer ??= Issuer::factory()->create();

        foreach ($daysAgo as $days) {
            $invoice = Invoice::factory()->for($user)->for($issuer)->create([
                'issued_at' => Carbon::now()->subDays($days),
                'status' => $status,
            ]);
            InvoiceItem::factory()->for($invoice)->create([
                'description' => $description,
                'unit_price' => $price,
                'quantity' => 2,
                'total_price' => $price * 2,
                'unit' => 'UN',
                ...$itemAttributes,
            ]);
        }
    }

    public function test_returns_empty_without_purchases(): void
    {
        $this->assertCount(0, $this->service->getRecurringItems(User::factory()->create()->id));
    }

    public function test_requires_three_distinct_purchase_days(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [0, 7]);
        $this->buy($user, [3, 3, 3], 'OVO');

        $this->assertCount(0, $this->service->getRecurringItems($user->id));
    }

    public function test_ignores_unauthorized_invoices_and_zero_prices(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [1, 8, 15], 'PENDENTE', 5.0, null, [], InvoiceStatus::Pending);
        $this->buy($user, [1, 8, 15], 'GRATIS', 0.0);

        $this->assertCount(0, $this->service->getRecurringItems($user->id));
    }

    public function test_only_counts_the_users_own_purchases(): void
    {
        $user = User::factory()->create();
        $this->buy(User::factory()->create(), [1, 8, 15]);

        $this->assertCount(0, $this->service->getRecurringItems($user->id));
    }

    public function test_interval_is_the_median_so_a_burst_does_not_skew_it(): void
    {
        $user = User::factory()->create();
        // espaços de 10, 10 e 60 dias → mediana 10
        $this->buy($user, [90, 80, 70, 10]);

        $item = $this->service->getRecurringItems($user->id)->first();

        $this->assertSame(10, $item->interval_days);
    }

    public function test_status_progression(): void
    {
        $user = User::factory()->create();
        // intervalo 10 dias, última compra há N dias
        $this->buy($user, [40, 30, 20, 4], 'OK');       // faltam 6 (> 2.5→ "soon" só ≤ 3)
        $this->buy($user, [40, 30, 20, 8], 'SOON');     // faltam 2
        $this->buy($user, [40, 30, 20, 10], 'DUE');     // hoje
        $this->buy($user, [70, 60, 50, 15], 'LATE');    // 15 ≥ 1.5×10
        $this->buy($user, [200, 190, 180], 'INACTIVE'); // parado há 180 > 30

        $status = $this->service->getRecurringItems($user->id)->pluck('status', 'description');

        $this->assertSame('ok', $status['OK']);
        $this->assertSame('soon', $status['SOON']);
        $this->assertSame('due', $status['DUE']);
        $this->assertSame('late', $status['LATE']);
        $this->assertSame('inactive', $status['INACTIVE']);
    }

    public function test_best_issuer_is_the_cheapest_current_price_and_estimates_saving(): void
    {
        $user = User::factory()->create();
        $expensive = Issuer::factory()->create();
        $cheap = Issuer::factory()->create();
        $this->buy($user, [30, 20, 5], 'LEITE', 8.0, $expensive);
        $this->buy($user, [15], 'LEITE', 6.0, $cheap);

        $item = $this->service->getRecurringItems($user->id)->first();

        $this->assertSame($cheap->id, $item->best_issuer->issuer_id);
        $this->assertSame(6.0, $item->best_issuer->price);
        $this->assertGreaterThan(0, $item->estimated_saving_per_month);
        $this->assertSame(2, $item->suggested_quantity);
    }

    public function test_stale_prices_lose_to_fresh_ones_and_give_no_saving(): void
    {
        $user = User::factory()->create();
        $old = Issuer::factory()->create();
        $recent = Issuer::factory()->create();
        $this->buy($user, [300, 280], 'LEITE', 1.0, $old);
        $this->buy($user, [20, 10, 1], 'LEITE', 9.0, $recent);

        $item = $this->service->getRecurringItems($user->id)->first();

        $this->assertSame($recent->id, $item->best_issuer->issuer_id);
    }

    public function test_units_are_separate_recurrences(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [20, 10, 1], 'BANANA', 5.0, null, ['unit' => 'KG']);
        $this->buy($user, [22, 12, 2], 'BANANA', 3.0, null, ['unit' => 'UN']);

        $this->assertCount(2, $this->service->getRecurringItems($user->id));
    }

    public function test_aliases_merge_different_descriptions(): void
    {
        $user = User::factory()->create();
        ProductAlias::create(['user_id' => $user->id, 'description' => 'LEITE INT 1L', 'canonical_name' => 'LEITE']);
        $this->buy($user, [20], 'LEITE');
        $this->buy($user, [10], 'LEITE INT 1L');
        $this->buy($user, [1], 'LEITE');

        $items = $this->service->getRecurringItems($user->id);

        $this->assertCount(1, $items);
        $this->assertSame('LEITE', $items->first()->description);
    }

    public function test_dismiss_and_restore(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [20, 10, 1]);

        $this->service->dismiss($user->id, 'LEITE');
        $this->service->dismiss($user->id, 'LEITE');
        $items = $this->service->getRecurringItems($user->id);

        $this->assertSame(1, RecurringDismissal::count());
        $this->assertCount(0, $this->service->filter($items));
        $this->assertCount(1, $this->service->filter($items, ['dismissed' => true, 'status' => 'all']));
        $this->assertSame(0, $this->service->summary($items)['products']);
        $this->assertSame(1, $this->service->summary($items)['dismissed_count']);

        $this->service->restore($user->id, 'LEITE');
        $this->assertCount(1, $this->service->filter($this->service->getRecurringItems($user->id)));
    }

    public function test_filter_by_status_search_and_sort(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [40, 30, 20, 4], 'ARROZ');
        $this->buy($user, [70, 60, 50, 15], 'FEIJAO');
        $items = $this->service->getRecurringItems($user->id);

        $this->assertSame(['FEIJAO', 'ARROZ'], $this->service->filter($items)->pluck('description')->all());
        $this->assertSame(['FEIJAO'], $this->service->filter($items, ['status' => 'late'])->pluck('description')->all());
        $this->assertSame(['ARROZ'], $this->service->filter($items, ['q' => 'arr'])->pluck('description')->all());
        $this->assertSame(['ARROZ', 'FEIJAO'], $this->service->filter($items, ['sort' => 'name'])->pluck('description')->all());
    }

    public function test_replenishment_list_contains_only_due_products(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $this->buy($user, [40, 30, 20, 10], 'DUE', 5.0, $issuer);
        $this->buy($user, [40, 30, 20, 4], 'OK', 5.0, $issuer);

        $created = $this->service->createReplenishmentList($user);

        $this->assertSame(1, $created['count']);
        $this->assertSame(['DUE'], $created['list']->items()->pluck('description')->all());
        $this->assertSame(2, $created['list']->items()->first()->quantity);
    }

    public function test_replenishment_list_is_null_when_nothing_is_due(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [40, 30, 20, 4]);

        $this->assertNull($this->service->createReplenishmentList($user));
        $this->assertSame(0, ShoppingList::count());
    }

    public function test_add_to_list_creates_a_new_list_when_none_given(): void
    {
        $user = User::factory()->create();

        $result = $this->service->addToList($user, null, ['description' => 'LEITE']);

        $this->assertSame($user->id, $result['list']->user_id);
        $this->assertSame(1, $result['item']->quantity);
    }

    public function test_dismissal_matches_regardless_of_case_and_accents(): void
    {
        $user = User::factory()->create();
        $this->buy($user, [20, 10, 1], 'FEIJÃO PRETO');
        RecurringDismissal::create(['user_id' => $user->id, 'description' => 'feijao preto']);

        $this->assertTrue($this->service->getRecurringItems($user->id)->first()->dismissed);
    }
}
