<?php

namespace Tests\Feature;

use App\Enums\ReportFrequency;
use App\Jobs\SendReportByEmailJob;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\ProductAlias;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Notifications\ReportByEmail;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Página de Relatórios: validação, totais coerentes, filtros, paginação, séries, CSV seguro,
 * e-mail e agendamento recorrente.
 */
class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = 'start_date=2026-06-01&end_date=2026-06-30';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function invoiceOn(User $user, string $date, float $total = 100, array $attributes = []): Invoice
    {
        return Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => Issuer::factory()->create()->id,
            'issued_at' => $date.' 10:00:00',
            'total_amount' => $total,
            ...$attributes,
        ]);
    }

    private function itemOn(Invoice $invoice, float $price, array $attributes = []): InvoiceItem
    {
        return InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'total_price' => $price, 'category_id' => null, ...$attributes]);
    }

    private function page(User $user, string $query = ''): array
    {
        return $this->actingAs($user)->get('/reports?'.self::PERIOD.($query ? '&'.$query : ''))->assertOk()->original->getData();
    }

    // ─── Validação ───────────────────────────────────────────────────────────────────────────

    public function test_invalid_filters_are_rejected_instead_of_breaking_the_query(): void
    {
        $user = User::factory()->create();
        $foreign = Category::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->get('/reports?start_date=abc')->assertSessionHasErrors('start_date');
        $this->actingAs($user)->get('/reports?start_date=2026-06-30&end_date=2026-06-01')->assertSessionHasErrors('end_date');
        $this->actingAs($user)->get('/reports?sort=drop')->assertSessionHasErrors('sort');
        $this->actingAs($user)->get('/reports?category_id='.$foreign->id)->assertSessionHasErrors('category_id');
        $this->actingAs($user)->post('/reports/generate', ['end_date' => 'lixo'])->assertSessionHasErrors('end_date');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports?category_id='.$foreign->id)->assertStatus(422);
    }

    // ─── Totais ──────────────────────────────────────────────────────────────────────────────

    public function test_total_is_the_invoice_total_net_of_discount_without_category_or_product_filter(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10', 90); // itens somam 100, nota paga por 90
        $this->itemOn($invoice, 60);
        $this->itemOn($invoice, 40);

        $summary = $this->page($user)['summary'];

        $this->assertSame(90.0, $summary->total_amount);
        $this->assertSame(2, $summary->total_items);
        $this->assertSame(1, $summary->total_invoices);
        $this->assertSame(90.0, $summary->average_ticket);
        $this->assertSame('Ticket médio', $summary->average_label);
        $this->assertFalse($summary->is_partial);
    }

    public function test_total_is_the_sum_of_matching_items_when_filtering_by_category_or_product(): void
    {
        $user = User::factory()->create();
        $food = Category::factory()->create(['user_id' => $user->id]);
        $invoice = $this->invoiceOn($user, '2026-06-10', 90);
        $this->itemOn($invoice, 60, ['category_id' => $food->id, 'description' => 'ARROZ']);
        $this->itemOn($invoice, 40, ['description' => 'SABAO']);

        $byCategory = $this->page($user, 'category_id='.$food->id)['summary'];
        $byProduct = $this->page($user, 'q=sabao')['summary'];

        $this->assertSame(60.0, $byCategory->total_amount);
        $this->assertTrue($byCategory->is_partial);
        $this->assertSame('Média por nota (filtro)', $byCategory->average_label);
        $this->assertSame(40.0, $byProduct->total_amount);
    }

    public function test_totals_ignore_other_users_data(): void
    {
        $user = User::factory()->create();
        $this->itemOn($this->invoiceOn(User::factory()->create(), '2026-06-10', 500), 500);

        $data = $this->page($user);

        $this->assertSame(0.0, $data['summary']->total_amount);
        $this->assertSame(0, $data['items']->total());
    }

    public function test_compares_total_with_the_previous_period(): void
    {
        $user = User::factory()->create();
        $this->itemOn($this->invoiceOn($user, '2026-06-15', 150), 150);   // atual: 11–20/06
        $this->itemOn($this->invoiceOn($user, '2026-06-05', 100), 100);   // anterior: 01–10/06

        $this->assertSame(50.0, $this->actingAs($user)->get('/reports?start_date=2026-06-11&end_date=2026-06-20')->original->getData()['summary']->delta_pct);
        $this->assertNull($this->actingAs($user)->get('/reports?start_date=2000-01-01&end_date=2026-06-30')->original->getData()['summary']->delta_pct);
    }

    // ─── Filtros ─────────────────────────────────────────────────────────────────────────────

    public function test_items_of_an_invoice_without_issuer_still_appear(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10', 30, ['issuer_id' => null]);
        $this->itemOn($invoice, 30);

        $items = $this->page($user)['items'];

        $this->assertSame(1, $items->total());
        $this->assertSame('Emissor não identificado', $items->first()->issuer_name);
    }

    public function test_no_category_filter_returns_only_uncategorized_items(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $invoice = $this->invoiceOn($user, '2026-06-10');
        $this->itemOn($invoice, 10, ['category_id' => $category->id]);
        $free = $this->itemOn($invoice, 20);

        $items = $this->page($user, 'category_id=none')['items'];

        $this->assertSame([$free->id], $items->getCollection()->pluck('item_id')->all());
    }

    public function test_product_search_matches_canonical_and_raw_names(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10');
        $a = $this->itemOn($invoice, 10, ['description' => 'CAFE PILAO 500G']);
        $b = $this->itemOn($invoice, 10, ['description' => 'COLOMBIANO XYZ']);
        $this->itemOn($invoice, 10, ['description' => 'SABAO EM PO']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'COLOMBIANO XYZ', 'canonical_name' => 'Café Especial']);

        $ids = fn (string $q) => $this->page($user, 'q='.urlencode($q))['items']->getCollection()->pluck('item_id')->sort()->values()->all();

        $this->assertSame([$a->id], $ids('pilao'));
        $this->assertSame([$b->id], $ids('especial'));
        $this->assertSame([$a->id, $b->id], $ids('caf'));
    }

    // ─── Paginação e ordenação ───────────────────────────────────────────────────────────────

    public function test_items_are_paginated_while_totals_cover_the_whole_period(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10', 550);
        InvoiceItem::factory()->count(55)->create(['invoice_id' => $invoice->id, 'total_price' => 10, 'category_id' => null]);

        $first = $this->page($user);
        $second = $this->page($user, 'page=2');

        $this->assertCount(50, $first['items']);
        $this->assertCount(5, $second['items']);
        $this->assertSame(55, $first['summary']->total_items);
        $this->assertSame(550.0, $first['summary']->total_amount);
        $this->assertStringContainsString(route('reports.index'), $first['items']->nextPageUrl());
        $this->assertStringContainsString('start_date=2026-06-01', $first['items']->nextPageUrl());
    }

    public function test_items_can_be_sorted_by_value_and_name(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10');
        $b = $this->itemOn($invoice, 300, ['description' => 'BANANA']);
        $a = $this->itemOn($invoice, 10, ['description' => 'ABACATE']);
        $c = $this->itemOn($invoice, 50, ['description' => 'CEBOLA']);

        $order = fn (string $sort) => $this->page($user, 'sort='.$sort)['items']->getCollection()->pluck('item_id')->all();

        $this->assertSame([$b->id, $c->id, $a->id], $order('highest'));
        $this->assertSame([$a->id, $c->id, $b->id], $order('lowest'));
        $this->assertSame([$a->id, $b->id, $c->id], $order('name'));
    }

    // ─── Séries ──────────────────────────────────────────────────────────────────────────────

    public function test_monthly_series_and_issuer_breakdown(): void
    {
        $user = User::factory()->create();
        $mercado = Issuer::factory()->create(['name' => 'Mercado A']);
        $feira = Issuer::factory()->create(['name' => 'Feira B']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $feira->id, 'nickname' => 'Feira do bairro']);
        $this->itemOn($this->invoiceOn($user, '2026-06-10', 200, ['issuer_id' => $mercado->id]), 200);
        $this->itemOn($this->invoiceOn($user, '2026-05-10', 50, ['issuer_id' => $feira->id]), 50);
        $this->itemOn($this->invoiceOn($user, '2026-06-20', 30, ['issuer_id' => $feira->id]), 30);

        $data = $this->page($user);

        $this->assertCount(12, $data['monthly']);
        $this->assertSame('2026-06', end($data['monthly'])['month']);
        $this->assertSame(230.0, end($data['monthly'])['total']);
        $this->assertSame(50.0, collect($data['monthly'])->firstWhere('month', '2026-05')['total']);
        $this->assertSame(['Mercado A', 'Feira do bairro'], array_column($data['byIssuer'], 'name'));
        $this->assertSame([], $this->page($user, 'issuer_id='.$mercado->id)['byIssuer']);
    }

    // ─── CSV ─────────────────────────────────────────────────────────────────────────────────

    public function test_csv_neutralizes_spreadsheet_formulas(): void
    {
        $user = User::factory()->pro()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10');
        $this->itemOn($invoice, 10, ['description' => '=HYPERLINK("http://x","clique")', 'unit' => 'UN']);
        $this->itemOn($invoice, 10, ['description' => '@SUM(A1)']);
        $this->itemOn($invoice, 10, ['description' => 'ARROZ NORMAL']);

        $csv = $this->actingAs($user)->get('/reports/csv?'.self::PERIOD)->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@SUM(A1)", $csv);
        $this->assertStringContainsString('ARROZ NORMAL', $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_csv_exports_every_item_and_respects_filters(): void
    {
        $user = User::factory()->pro()->create();
        $category = Category::factory()->create(['user_id' => $user->id]);
        $invoice = $this->invoiceOn($user, '2026-06-10');
        InvoiceItem::factory()->count(60)->create(['invoice_id' => $invoice->id, 'category_id' => null]);
        $this->itemOn($invoice, 5, ['category_id' => $category->id, 'description' => 'SO ESTE']);

        $all = $this->actingAs($user)->get('/reports/csv?'.self::PERIOD)->streamedContent();
        $filtered = $this->actingAs($user)->get('/reports/csv?'.self::PERIOD.'&category_id='.$category->id)->streamedContent();

        $this->assertSame(62, substr_count($all, "\n"));       // cabeçalho + 61 itens
        $this->assertSame(2, substr_count($filtered, "\n"));
        $this->assertStringContainsString('SO ESTE', $filtered);
    }

    public function test_exports_by_get_link_are_pro_only(): void
    {
        $free = User::factory()->create();

        $this->actingAs($free)->get('/reports/csv')->assertRedirect(route('subscription.upgrade'));
        $this->actingAs($free)->get('/reports/pdf')->assertRedirect(route('subscription.upgrade'));
    }

    public function test_pdf_export_flags_truncation(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10');
        InvoiceItem::factory()->count(5)->create(['invoice_id' => $invoice->id, 'category_id' => null]);

        $data = app(ReportService::class)->buildReportData($user->id, ['start_date' => '2026-06-01', 'end_date' => '2026-06-30'], 3);

        $this->assertCount(3, $data['items']);
        $this->assertTrue($data['items_truncated']);
        $this->assertFalse(app(ReportService::class)->buildReportData($user->id, ['start_date' => '2026-06-01', 'end_date' => '2026-06-30'], 10)['items_truncated']);
    }

    // ─── API ─────────────────────────────────────────────────────────────────────────────────

    public function test_api_generate_paginates_items_and_exposes_new_blocks(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceOn($user, '2026-06-10', 300);
        InvoiceItem::factory()->count(30)->create(['invoice_id' => $invoice->id, 'total_price' => 10, 'category_id' => null]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports?'.self::PERIOD.'&per_page=10&page=2')->assertOk();

        $response->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.items_meta', ['current_page' => 2, 'last_page' => 3, 'per_page' => 10, 'total' => 30])
            ->assertJsonPath('data.summary.total_amount', 300)
            ->assertJsonPath('data.summary.total_items', 30)
            ->assertJsonCount(12, 'data.monthly')
            ->assertJsonPath('data.filters.sort', 'recent');
        $this->assertArrayHasKey('delta_pct', $response->json('data.summary'));
        $this->assertArrayHasKey('byIssuer', $response->json('data'));
    }

    public function test_api_generate_rejects_invalid_parameters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports?per_page=500')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports?sort=nope')->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports/email', ['format' => 'pdf', 'start_date' => 'x'])->assertStatus(402);
        $this->actingAs(User::factory()->pro()->create(), 'sanctum')->postJson('/api/v1/reports/email', ['format' => 'pdf', 'start_date' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('start_date');
    }

    // ─── Tela: Pro x gratuito ─────────────────────────────────────────────────────────────────

    public function test_export_buttons_link_with_filters_for_pro_and_to_upgrade_for_free(): void
    {
        $pro = $this->actingAs(User::factory()->pro()->create())->get('/reports?'.self::PERIOD.'&q=arroz')->assertOk();
        $pro->assertSee(e(route('reports.csv', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'q' => 'arroz', 'sort' => 'recent'])), false)
            ->assertSee('reportEmailModal', false);

        $this->actingAs(User::factory()->create())->get('/reports')->assertOk()
            ->assertSee(route('subscription.upgrade'), false)
            ->assertDontSee('reportEmailModal', false)
            ->assertDontSee('reports/csv', false);
    }

    // ─── E-mail ──────────────────────────────────────────────────────────────────────────────

    public function test_web_email_queues_the_report_with_current_filters(): void
    {
        Queue::fake();
        $user = User::factory()->pro()->create();

        $this->actingAs($user)->postJson('/reports/email', ['format' => 'csv', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30', 'q' => 'arroz'])
            ->assertOk()->assertJson(['scheduled' => true]);

        Queue::assertPushed(SendReportByEmailJob::class, 1);
    }

    public function test_web_email_validates_format_and_is_pro_only_with_json_402(): void
    {
        $this->actingAs(User::factory()->pro()->create())->postJson('/reports/email', ['format' => 'xls'])
            ->assertStatus(422)->assertJsonValidationErrors('format');

        $this->actingAs(User::factory()->create())->postJson('/reports/email', ['format' => 'pdf'])
            ->assertStatus(402)->assertJsonPath('upgrade_required', true);
    }

    public function test_job_mentions_the_report_period_in_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->pro()->create();

        (new SendReportByEmailJob($user->id, 'csv', ['start_date' => '2026-06-01', 'end_date' => '2026-06-30']))->handle(app(ReportService::class));

        Notification::assertSentTo($user, ReportByEmail::class, function ($notification) use ($user) {
            return collect($notification->toMail($user)->introLines)->contains('Período: 01/06/2026 a 30/06/2026.');
        });
    }

    // ─── Agendamento ─────────────────────────────────────────────────────────────────────────

    public function test_schedule_can_be_saved_updated_and_cancelled(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user)->putJson('/reports/schedule', ['frequency' => 'weekly', 'format' => 'pdf'])
            ->assertOk()->assertJsonPath('frequency', 'weekly');
        $this->actingAs($user)->putJson('/reports/schedule', ['frequency' => 'monthly', 'format' => 'csv'])->assertOk();

        $this->assertDatabaseCount('report_schedules', 1);
        $this->assertDatabaseHas('report_schedules', ['user_id' => $user->id, 'frequency' => 'monthly', 'format' => 'csv']);

        $this->actingAs($user)->deleteJson('/reports/schedule')->assertOk();
        $this->assertDatabaseCount('report_schedules', 0);
    }

    public function test_schedule_validates_input_and_saving_is_pro_only(): void
    {
        $this->actingAs(User::factory()->pro()->create())->putJson('/reports/schedule', ['frequency' => 'daily', 'format' => 'pdf'])
            ->assertStatus(422)->assertJsonValidationErrors('frequency');

        $this->actingAs(User::factory()->create())->putJson('/reports/schedule', ['frequency' => 'weekly', 'format' => 'pdf'])->assertStatus(402);
        $this->assertDatabaseCount('report_schedules', 0);
    }

    public function test_api_schedule_endpoints(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports/schedule')->assertOk()->assertJsonPath('data', null);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/reports/schedule', ['frequency' => 'monthly', 'format' => 'pdf'])
            ->assertOk()->assertJsonPath('data.frequency', 'monthly')->assertJsonStructure(['data' => ['frequency_label', 'next_run', 'last_sent_on']]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports/schedule')->assertJsonPath('data.format', 'pdf');
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/reports/schedule')->assertOk();
        $this->actingAs(User::factory()->create(), 'sanctum')->putJson('/api/v1/reports/schedule', ['frequency' => 'weekly', 'format' => 'pdf'])->assertStatus(402);
    }

    public function test_schedule_is_removed_with_the_user(): void
    {
        $user = User::factory()->pro()->create();
        ReportSchedule::create(['user_id' => $user->id, 'frequency' => 'weekly', 'format' => 'pdf']);

        $user->delete();

        $this->assertDatabaseCount('report_schedules', 0);
    }

    public function test_frequencies_know_their_period_and_next_run(): void
    {
        $monday = Carbon::parse('2026-09-21'); // segunda
        $this->assertSame(['start_date' => '2026-09-14', 'end_date' => '2026-09-20'], ReportFrequency::Weekly->periodFor($monday));
        $this->assertTrue(ReportFrequency::Weekly->isDueOn($monday));
        $this->assertFalse(ReportFrequency::Weekly->isDueOn($monday->copy()->addDay()));

        $first = Carbon::parse('2026-10-01');
        $this->assertSame(['start_date' => '2026-09-01', 'end_date' => '2026-09-30'], ReportFrequency::Monthly->periodFor($first));
        $this->assertSame(['start_date' => '2026-02-01', 'end_date' => '2026-02-28'], ReportFrequency::Monthly->periodFor(Carbon::parse('2026-03-01')));
        $this->assertSame('2026-09-28', ReportFrequency::Weekly->nextRunAfter($monday)->toDateString());
        $this->assertSame('2026-11-01', ReportFrequency::Monthly->nextRunAfter($first)->toDateString());
    }

    public function test_command_sends_due_schedules_once_and_only_for_pro_users(): void
    {
        Queue::fake();
        $weekly = User::factory()->pro()->create();
        $monthly = User::factory()->pro()->create();
        $free = User::factory()->create();
        $alreadySent = User::factory()->pro()->create();
        ReportSchedule::create(['user_id' => $weekly->id, 'frequency' => 'weekly', 'format' => 'pdf']);
        ReportSchedule::create(['user_id' => $monthly->id, 'frequency' => 'monthly', 'format' => 'csv']);
        ReportSchedule::create(['user_id' => $free->id, 'frequency' => 'weekly', 'format' => 'pdf']);
        ReportSchedule::create(['user_id' => $alreadySent->id, 'frequency' => 'weekly', 'format' => 'pdf', 'last_sent_on' => '2026-09-21']);

        Carbon::setTestNow('2026-09-21 06:00:00'); // segunda, não é dia 1º
        $this->artisan('reports:send-scheduled')->assertSuccessful();

        Queue::assertPushed(SendReportByEmailJob::class, 1);
        $this->assertSame('2026-09-21', ReportSchedule::where('user_id', $weekly->id)->first()->last_sent_on->toDateString());
        $this->assertNull(ReportSchedule::where('user_id', $free->id)->first()->last_sent_on);

        $this->artisan('reports:send-scheduled')->assertSuccessful(); // mesmo dia: não reenvia
        Queue::assertPushed(SendReportByEmailJob::class, 1);

        Carbon::setTestNow('2026-10-01 06:00:00'); // dia 1º (quinta): só o mensal
        $this->artisan('reports:send-scheduled')->assertSuccessful();
        Queue::assertPushed(SendReportByEmailJob::class, 2);
    }
}
