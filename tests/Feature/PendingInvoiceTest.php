<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Events\InvoiceImported;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PendingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    // cUF 35 | AAMM 2609 | CNPJ 12345678000190 (o da fixture do portal) | mod 65 | serie 001 | nNF 123 | tpEmis | cNF | cDV
    private const CONTINGENCY_KEY = '35260912345678000190650010000001239123456780';

    private const NORMAL_KEY = '35260912345678000190650010000001231123456780';

    private function qrUrl(string $key): string
    {
        return "https://nfce.exemplo.gov.br/consulta?p={$key}|2|1|15|45.90|abcdef|000001|HASH";
    }

    private function fakePortalWithoutData(): void
    {
        Http::fake(['nfce.exemplo.gov.br/*' => Http::response('<html><body>Nota não encontrada</body></html>', 200)]);
    }

    private function fakePortalWithNote(): void
    {
        Http::fake(['nfce.exemplo.gov.br/*' => Http::response(
            file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html')),
            200
        )]);
    }

    // ─── import por QR Code ─────────────────────────────────────────────────

    public function test_web_qr_import_of_unauthorized_contingency_note_creates_pending_invoice(): void
    {
        $this->fakePortalWithoutData();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('my-purchases.import-qrcode'), [
            'qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY),
        ]);

        $invoice = Invoice::includingUnauthorized()->where('user_id', $user->id)->firstOrFail();
        $response->assertOk()->assertJson(['redirect' => route('my-purchases.detail', $invoice->id)]);
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertSame(self::CONTINGENCY_KEY, $invoice->access_key);
        $this->assertSame('45.90', $invoice->total_amount);
        $this->assertSame('2026-09-15', $invoice->issued_at->toDateString());
        $this->assertSame($this->qrUrl(self::CONTINGENCY_KEY), $invoice->qrcode_url);
        $this->assertNull($invoice->issuer_id);
    }

    public function test_api_qr_import_of_pending_note_exposes_status_and_explanation_without_dispatching_event(): void
    {
        Event::fake([InvoiceImported::class]);
        $this->fakePortalWithoutData();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY)])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pendente de autorização')
            ->assertJsonPath('data.status_description', InvoiceStatus::Pending->description());

        Event::assertNotDispatched(InvoiceImported::class);
    }

    public function test_pending_note_never_creates_an_issuer_without_name(): void
    {
        $this->fakePortalWithoutData();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY)])
            ->assertStatus(201);

        $this->assertDatabaseCount('issuers', 0);
    }

    public function test_pending_note_reuses_issuer_already_known_by_cnpj(): void
    {
        $this->fakePortalWithoutData();
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['cnpj' => '12345678000190']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY)])
            ->assertStatus(201);

        $this->assertDatabaseCount('issuers', 1);
        $this->assertSame($issuer->id, Invoice::includingUnauthorized()->firstOrFail()->issuer_id);
    }

    public function test_qr_import_without_data_on_normal_emission_is_rejected_instead_of_creating_empty_invoice(): void
    {
        $this->fakePortalWithoutData();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $this->qrUrl(self::NORMAL_KEY)])
            ->assertStatus(422);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_reimporting_a_pending_note_after_authorization_completes_it_instead_of_returning_409(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->pending()->create(['access_key' => self::CONTINGENCY_KEY]);
        $this->fakePortalWithNote();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY)])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'authorized');

        $this->assertSame(1, Invoice::includingUnauthorized()->count());
        $this->assertSame(1, Invoice::firstOrFail()->items()->count());
    }

    public function test_reimporting_an_authorized_note_still_returns_409(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['access_key' => self::CONTINGENCY_KEY]);
        $this->fakePortalWithNote();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY)])
            ->assertStatus(409);
    }

    // ─── visibilidade e totais ──────────────────────────────────────────────

    public function test_pending_note_is_excluded_from_default_queries_and_totals(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['total_amount' => 100, 'issued_at' => now()]);
        Invoice::factory()->for($user)->pending()->create(['total_amount' => 50, 'issued_at' => now()]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, Invoice::includingUnauthorized()->count());
        $this->assertSame(1, $user->invoices()->count());
        $this->assertEquals(100, $user->invoices()->sum('total_amount'));
    }

    public function test_purchases_list_shows_pending_note_but_stats_ignore_it(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['total_amount' => 100, 'issued_at' => now()]);
        Invoice::factory()->for($user)->pending()->create(['total_amount' => 50, 'issued_at' => now()]);

        $this->actingAs($user)
            ->get(route('my-purchases.index'))
            ->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 2)
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('totalAmount', 100.0)
            ->assertSee('Pendente de autorização');
    }

    public function test_api_list_includes_pending_note_with_status(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->pending()->create(['issued_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_detail_page_explains_what_a_contingency_note_is(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->pending()->create();

        $this->actingAs($user)
            ->get(route('my-purchases.detail', $invoice->id))
            ->assertOk()
            ->assertSee('Nota em contingência')
            ->assertSee('não entra nos seus totais');
    }

    public function test_api_show_opens_pending_note(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->pending()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_pending_note_of_another_user_is_still_forbidden(): void
    {
        $invoice = Invoice::factory()->pending()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('my-purchases.detail', $invoice->id))
            ->assertForbidden();
    }

    // ─── invoices:reconcile-pending ─────────────────────────────────────────

    public function test_reconcile_promotes_pending_note_once_portal_has_data(): void
    {
        Event::fake([InvoiceImported::class]);
        $this->fakePortalWithNote();
        $pending = Invoice::factory()->pending()->create([
            'access_key' => self::CONTINGENCY_KEY,
            'qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY),
        ]);

        $this->artisan('invoices:reconcile-pending')
            ->expectsOutput('1 nota(s) confirmada(s), 0 expirada(s).')
            ->assertSuccessful();

        $invoice = Invoice::findOrFail($pending->id);
        $this->assertSame(InvoiceStatus::Authorized, $invoice->status);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertNotNull($invoice->issuer_id);
        Event::assertDispatched(InvoiceImported::class);
    }

    public function test_reconcile_keeps_note_pending_while_portal_has_no_data(): void
    {
        Event::fake([InvoiceImported::class]);
        $this->fakePortalWithoutData();
        $pending = Invoice::factory()->pending()->create(['qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY)]);

        $this->artisan('invoices:reconcile-pending')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Pending, Invoice::includingUnauthorized()->findOrFail($pending->id)->status);
        Event::assertNotDispatched(InvoiceImported::class);
    }

    public function test_reconcile_failure_on_one_note_does_not_stop_the_others(): void
    {
        Http::fake([
            'nfce.exemplo.gov.br/falha*' => Http::response('', 503),
            'nfce.exemplo.gov.br/*' => Http::response(file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html')), 200),
        ]);
        $failing = Invoice::factory()->pending()->create(['qrcode_url' => 'https://nfce.exemplo.gov.br/falha?p=1']);
        $working = Invoice::factory()->pending()->create([
            'access_key' => self::CONTINGENCY_KEY,
            'qrcode_url' => $this->qrUrl(self::CONTINGENCY_KEY),
        ]);

        $this->artisan('invoices:reconcile-pending')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Pending, Invoice::includingUnauthorized()->findOrFail($failing->id)->status);
        $this->assertSame(InvoiceStatus::Authorized, Invoice::includingUnauthorized()->findOrFail($working->id)->status);
    }

    public function test_reconcile_expires_old_pending_notes_without_querying_the_portal(): void
    {
        Http::fake();
        $old = Invoice::factory()->pending()->create([
            'created_at' => now()->subDays(InvoiceStatus::PENDING_EXPIRATION_DAYS + 1),
        ]);

        $this->artisan('invoices:reconcile-pending')
            ->expectsOutput('0 nota(s) confirmada(s), 1 expirada(s).')
            ->assertSuccessful();

        $this->assertSame(InvoiceStatus::Expired, Invoice::includingUnauthorized()->findOrFail($old->id)->status);
        Http::assertNothingSent();
    }

    public function test_reconcile_does_not_touch_authorized_notes(): void
    {
        Http::fake();
        Invoice::factory()->create();

        $this->artisan('invoices:reconcile-pending')->assertSuccessful();

        Http::assertNothingSent();
    }
}
