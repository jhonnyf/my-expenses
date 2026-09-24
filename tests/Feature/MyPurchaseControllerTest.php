<?php

namespace Tests\Feature;

use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\ProductAlias;
use App\Models\QrCodeRead;
use App\Models\User;
use App\Services\NFCeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MyPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ───────────────────────────────────────────────────────────────

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/my-purchases')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200);
    }

    public function test_index_lists_only_current_user_invoices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'issued_at' => now()]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id, 'issued_at' => now()]);

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200)
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    public function test_index_filters_invoices_by_issuer_name(): void
    {
        $user = User::factory()->create();
        $market = Issuer::factory()->create(['name' => 'Mercado Bom Preço']);
        $pharmacy = Issuer::factory()->create(['name' => 'Farmácia Saúde']);

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $market->id, 'issued_at' => now()]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $pharmacy->id, 'issued_at' => now()]);

        $this->actingAs($user)
            ->get('/my-purchases?search=Mercado')
            ->assertStatus(200)
            ->assertViewHas('records', fn ($records) => $records->total() === 1)
            ->assertViewHas('search', 'Mercado');
    }

    public function test_index_returns_spending_stats(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'total_amount' => 100,
            'issued_at' => now(),
        ]);
        Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'total_amount' => 50,
            'issued_at' => now(),
        ]);

        // O controller compara strings 'Y-m-d' (meia-noite implícita) — precisa zerar a
        // hora aqui também, senão diffInDays() retorna fração de dia (não truncada).
        $days = now()->startOfMonth()->startOfDay()->diffInDays(now()->startOfDay()) + 1;

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200)
            ->assertViewHas('totalCount', 2)
            ->assertViewHas('totalAmount', 150.0)
            ->assertViewHas('dailyAverage', 150.0 / $days)
            ->assertViewHas('averageTicket', 75.0);
    }

    public function test_index_default_period_excludes_invoices_outside_current_month(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 100, 'issued_at' => now()]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 50, 'issued_at' => now()->subYear()]);

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200)
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('totalAmount', 100.0);
    }

    public function test_index_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 100, 'issued_at' => now()->subDays(5)]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id, 'total_amount' => 50, 'issued_at' => now()->subDays(40)]);

        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $this->actingAs($user)
            ->get("/my-purchases?start_date={$startDate}&end_date={$endDate}")
            ->assertStatus(200)
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('totalAmount', 100.0)
            ->assertViewHas('filters', ['start_date' => $startDate, 'end_date' => $endDate]);
    }

    public function test_index_combines_search_and_date_range_filters(): void
    {
        $user = User::factory()->create();
        $market = Issuer::factory()->create(['name' => 'Mercado Bom Preço']);
        $pharmacy = Issuer::factory()->create(['name' => 'Farmácia Saúde']);

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $market->id, 'issued_at' => now()]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $pharmacy->id, 'issued_at' => now()]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $market->id, 'issued_at' => now()->subYear()]);

        $this->actingAs($user)
            ->get('/my-purchases?search=Mercado')
            ->assertStatus(200)
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    // ─── uploadForm ──────────────────────────────────────────────────────────

    public function test_upload_form_redirects_unauthenticated_user(): void
    {
        $this->get('/my-purchases/upload')->assertRedirect('/login');
    }

    public function test_upload_form_returns_200(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/my-purchases/upload')
            ->assertStatus(200);
    }

    // ─── detail ──────────────────────────────────────────────────────────────

    public function test_detail_redirects_unauthenticated_user(): void
    {
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['issuer_id' => $issuer->id]);

        $this->get("/my-purchases/detail/{$invoice->id}")->assertRedirect('/login');
    }

    public function test_detail_returns_404_for_invoice_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(404);
    }

    public function test_detail_returns_200_for_own_invoice(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(200)
            ->assertViewHas('invoice');
    }

    public function test_detail_shows_canonical_product_name_when_aliased(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ 5KG']);
        ProductAlias::create(['user_id' => $user->id, 'description' => 'ARROZ 5KG', 'canonical_name' => 'Arroz Branco 5kg']);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(200)
            ->assertSee('Arroz Branco 5kg');
    }

    public function test_detail_shows_favorited_product_icon_as_active(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        InvoiceItem::factory()->for($invoice)->create(['description' => 'ARROZ BRANCO 5KG']);
        FavoriteProduct::factory()->for($user)->create(['canonical_name' => 'ARROZ BRANCO 5KG']);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(200)
            ->assertSee('ki-filled ki-heart text-xs text-destructive', false)
            ->assertSee('Remover aviso de queda de preço');
    }

    public function test_detail_shows_edit_nickname_button_for_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(200)
            ->assertSee('data-action="edit-nickname"', false)
            ->assertSee('data-issuer-id="'.$issuer->id.'"', false);
    }

    // ─── upload (XML) ────────────────────────────────────────────────────────

    public function test_upload_redirects_unauthenticated_user(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $this->post('/my-purchases/upload', ['xml' => $file])->assertRedirect('/login');
    }

    public function test_upload_xml_creates_invoice_and_redirects_to_detail(): void
    {
        $user = User::factory()->create();
        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $response = $this->actingAs($user)
            ->post('/my-purchases/upload', ['xml' => $file]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertNotNull($invoice);
        $response->assertRedirect(route('my-purchases.detail', $invoice->id));
    }

    public function test_upload_xml_rejects_duplicate_invoice(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'access_key' => '35260600000000000191650010000012341234567890',
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $this->actingAs($user)
            ->post('/my-purchases/upload', ['xml' => $file])
            ->assertSessionHasErrors(['xml']);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_upload_validates_file_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/my-purchases/upload', [])
            ->assertSessionHasErrors(['xml']);
    }

    // ─── importByQrCode ──────────────────────────────────────────────────────

    public function test_import_by_qr_code_returns_json_redirect_without_reloading(): void
    {
        $user = User::factory()->create();
        $chave = '35260700000000000191650010000098765123456789';

        $this->mock(NFCeService::class, function ($mock) use ($chave) {
            $mock->shouldReceive('extrairChaveDeUrl')->once()->andReturn($chave);
            $mock->shouldReceive('isCertificadoConfigurado')->once()->andReturn(false);
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => ['itens' => [['numero_item' => 1]]], 'html' => '']);
            $mock->shouldReceive('normalizarDadosPortal')->once()->andReturn([
                'chave' => $chave,
                'emitente' => ['cnpj' => '12345678000199', 'nome' => 'Loja Teste'],
                'itens' => [],
                'total' => ['valor_nota' => 10.0],
                'pagamento' => [],
            ]);
        });

        $response = $this->actingAs($user)->postJson(route('my-purchases.import-qrcode'), [
            'qrcode_url' => "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1",
        ]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertNotNull($invoice);
        $response->assertOk()->assertExactJson(['redirect' => route('my-purchases.detail', $invoice->id)]);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'status' => 'success',
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_import_by_qr_code_returns_json_error_without_reloading(): void
    {
        $user = User::factory()->create();

        $this->mock(NFCeService::class, function ($mock) {
            $mock->shouldReceive('extrairChaveDeUrl')->once()->andReturn(null);
        });

        $response = $this->actingAs($user)->postJson(route('my-purchases.import-qrcode'), [
            'qrcode_url' => 'https://www.nfce.fazenda.sp.gov.br/invalido',
        ]);

        $response->assertStatus(422)->assertExactJson([
            'errors' => ['qrcode_url' => ['Não foi possível extrair a chave de acesso da URL.']],
        ]);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'status' => 'error',
            'error_message' => 'Não foi possível extrair a chave de acesso da URL.',
            'invoice_id' => null,
        ]);
    }

    public function test_import_by_qr_code_rejects_duplicate_invoice_via_json(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $chave = '35260700000000000191650010000098765123456789';

        Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'access_key' => $chave,
        ]);

        $this->mock(NFCeService::class, function ($mock) use ($chave) {
            $mock->shouldReceive('extrairChaveDeUrl')->once()->andReturn($chave);
            $mock->shouldReceive('isCertificadoConfigurado')->once()->andReturn(false);
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => ['itens' => [['numero_item' => 1]]], 'html' => '']);
            $mock->shouldReceive('normalizarDadosPortal')->once()->andReturn(['chave' => $chave]);
        });

        $response = $this->actingAs($user)->postJson(route('my-purchases.import-qrcode'), [
            'qrcode_url' => "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1",
        ]);

        $response->assertStatus(422)->assertExactJson([
            'errors' => ['qrcode_url' => ['Esta nota fiscal já foi importada anteriormente.']],
        ]);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'status' => 'error',
            'error_message' => 'Esta nota fiscal já foi importada anteriormente.',
            'invoice_id' => null,
        ]);
    }

    public function test_import_by_qr_code_still_redirects_for_non_json_requests(): void
    {
        $user = User::factory()->create();
        $chave = '35260700000000000191650010000098765123456789';

        $this->mock(NFCeService::class, function ($mock) use ($chave) {
            $mock->shouldReceive('extrairChaveDeUrl')->once()->andReturn($chave);
            $mock->shouldReceive('isCertificadoConfigurado')->once()->andReturn(false);
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => ['itens' => [['numero_item' => 1]]], 'html' => '']);
            $mock->shouldReceive('normalizarDadosPortal')->once()->andReturn([
                'chave' => $chave,
                'emitente' => ['cnpj' => '12345678000199', 'nome' => 'Loja Teste'],
                'itens' => [],
                'total' => ['valor_nota' => 10.0],
                'pagamento' => [],
            ]);
        });

        $response = $this->actingAs($user)->post(route('my-purchases.import-qrcode'), [
            'qrcode_url' => "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1",
        ]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $response->assertRedirect(route('my-purchases.detail', $invoice->id));
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'status' => 'success',
            'invoice_id' => $invoice->id,
        ]);
    }

    // ─── /api/nfce/upload (rota legada exposta sob /api) ────────────────────

    public function test_api_nfce_upload_returns_json_on_success_without_accept_header(): void
    {
        $user = User::factory()->create();
        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $response = $this->actingAs($user)->post('/api/nfce/upload', ['xml' => $file]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertNotNull($invoice);
        $response->assertOk()->assertExactJson(['redirect' => route('my-purchases.detail', $invoice->id)]);
    }

    public function test_api_nfce_upload_returns_json_error_without_accept_header(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'issuer_id' => $issuer->id,
            'access_key' => '35260600000000000191650010000012341234567890',
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $response = $this->actingAs($user)->post('/api/nfce/upload', ['xml' => $file]);

        $response->assertStatus(422)->assertExactJson([
            'errors' => ['xml' => ['Esta nota fiscal já foi importada anteriormente.']],
        ]);
        $this->assertDatabaseCount('invoices', 1);
    }

    private function period(string $start, string $end): string
    {
        return "start_date={$start}&end_date={$end}";
    }

    private function recordsOf(User $user, string $query): Collection
    {
        return $this->actingAs($user)->get('/my-purchases?'.$query)->viewData('records')->getCollection();
    }

    private function invoiceAt(User $user, string $date, float $total, array $attributes = []): Invoice
    {
        return Invoice::factory()->create([
            'user_id' => $user->id,
            'issued_at' => $date.' 10:00:00',
            'total_amount' => $total,
            ...$attributes,
        ]);
    }

    // ─── Contagem: pendentes ficam de fora dos totais, mas aparecem à parte ─────────────

    public function test_index_reports_unconfirmed_notes_separately_from_totals(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();
        $this->invoiceAt($user, $today, 100);
        Invoice::factory()->pending()->create(['user_id' => $user->id, 'issued_at' => $today.' 09:00:00']);

        $this->actingAs($user)->get('/my-purchases')
            ->assertViewHas('records', fn ($records) => $records->total() === 2)
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('unconfirmedCount', 1)
            ->assertSee('aguardando confirmação');
    }

    // ─── Busca ───────────────────────────────────────────────────────────────────────────────

    public function test_index_search_matches_official_name_nickname_cnpj_and_number(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();
        $byName = Issuer::factory()->create(['name' => 'Atacadao Central', 'cnpj' => '11111111000111']);
        $byNickname = Issuer::factory()->create(['name' => 'Razao Social X', 'cnpj' => '22222222000122']);
        $byCnpj = Issuer::factory()->create(['name' => 'Loja Y', 'cnpj' => '12345678000190']);
        $byNumber = Issuer::factory()->create(['name' => 'Loja Z', 'cnpj' => '33333333000133']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $byNickname->id, 'nickname' => 'Feira do Bairro']);
        $a = $this->invoiceAt($user, $today, 10, ['issuer_id' => $byName->id, 'number' => '100001']);
        $b = $this->invoiceAt($user, $today, 10, ['issuer_id' => $byNickname->id, 'number' => '100002']);
        $c = $this->invoiceAt($user, $today, 10, ['issuer_id' => $byCnpj->id, 'number' => '100003']);
        $d = $this->invoiceAt($user, $today, 10, ['issuer_id' => $byNumber->id, 'number' => '987654']);

        $ids = fn (string $term) => $this->recordsOf($user, 'search='.urlencode($term))->pluck('id')->all();

        $this->assertSame([$a->id], $ids('atacadao'));
        $this->assertSame([$b->id], $ids('feira'));
        $this->assertSame([$c->id], $ids('12.345.678/0001-90'));
        $this->assertSame([$d->id], $ids('987654'));
        $this->assertSame([], $ids('inexistente'));
    }

    public function test_index_search_never_reveals_other_users_notes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Segredo Ltda']);
        $this->invoiceAt($other, now()->toDateString(), 10, ['issuer_id' => $issuer->id]);

        $this->assertCount(0, $this->recordsOf($user, 'search=Segredo'));
    }

    // ─── Filtros ───────────────────────────────────────────────────────────────────────────

    public function test_index_filters_by_issuer_and_status_and_totals_follow_the_filter(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();
        $issuerA = Issuer::factory()->create();
        $issuerB = Issuer::factory()->create();
        $a = $this->invoiceAt($user, $today, 30, ['issuer_id' => $issuerA->id]);
        $this->invoiceAt($user, $today, 70, ['issuer_id' => $issuerB->id]);
        $pending = Invoice::factory()->pending()->create(['user_id' => $user->id, 'issued_at' => $today.' 08:00:00']);

        $this->assertSame([$a->id], $this->recordsOf($user, 'issuer_id='.$issuerA->id)->pluck('id')->all());
        $this->assertSame([$pending->id], $this->recordsOf($user, 'status=pending')->pluck('id')->all());

        $this->actingAs($user)->get('/my-purchases?issuer_id='.$issuerA->id)
            ->assertViewHas('totalAmount', 30.0)
            ->assertViewHas('totalCount', 1);
    }

    public function test_index_offers_issuers_of_the_user_including_nickname_as_filter_options(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create(['name' => 'Zeta Ltda']);
        $issuerB = Issuer::factory()->create(['name' => 'Alfa Ltda']);
        $this->invoiceAt($user, now()->toDateString(), 10, ['issuer_id' => $issuerA->id]);
        $this->invoiceAt($user, now()->toDateString(), 10, ['issuer_id' => $issuerB->id]);
        $this->invoiceAt(User::factory()->create(), now()->toDateString(), 10);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuerA->id, 'nickname' => 'Mercadinho']);

        $this->actingAs($user)->get('/my-purchases')->assertViewHas('issuerOptions', [
            ['id' => $issuerB->id, 'name' => 'Alfa Ltda'],
            ['id' => $issuerA->id, 'name' => 'Mercadinho'],
        ]);
    }

    public function test_index_rejects_invalid_sort_and_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/my-purchases?sort=drop_table')->assertSessionHasErrors('sort');
        $this->actingAs($user)->get('/my-purchases?status=xyz')->assertSessionHasErrors('status');
    }

    // ─── Ordenação ───────────────────────────────────────────────────────────────────────────

    public function test_index_sorts_by_date_and_value(): void
    {
        $user = User::factory()->create();
        $old = $this->invoiceAt($user, now()->subDays(5)->toDateString(), 900);
        $mid = $this->invoiceAt($user, now()->subDays(3)->toDateString(), 10);
        $new = $this->invoiceAt($user, now()->subDay()->toDateString(), 200);
        $range = $this->period(now()->subDays(10)->toDateString(), now()->toDateString());

        $order = fn (string $sort) => $this->recordsOf($user, "{$range}&sort={$sort}")->pluck('id')->all();

        $this->assertSame([$new->id, $mid->id, $old->id], $order('recent'));
        $this->assertSame([$old->id, $mid->id, $new->id], $order('oldest'));
        $this->assertSame([$old->id, $new->id, $mid->id], $order('highest'));
        $this->assertSame([$mid->id, $new->id, $old->id], $order('lowest'));
    }

    // ─── Delta contra o período anterior ────────────────────────────────────────────────

    public function test_index_compares_total_with_the_previous_period_of_same_length(): void
    {
        $user = User::factory()->create();
        // período atual: 11–20/06 (10 dias); anterior: 01–10/06
        $this->invoiceAt($user, '2026-06-15', 150);
        $this->invoiceAt($user, '2026-06-05', 100);
        $this->invoiceAt($user, '2026-05-25', 9999); // fora dos dois períodos

        $this->actingAs($user)->get('/my-purchases?'.$this->period('2026-06-11', '2026-06-20'))
            ->assertViewHas('deltaPct', 50.0)
            ->assertSee('50,0%');
    }

    public function test_index_delta_is_null_without_spending_in_previous_period_and_for_all_time(): void
    {
        $user = User::factory()->create();
        $this->invoiceAt($user, '2026-06-15', 150);

        $this->actingAs($user)->get('/my-purchases?'.$this->period('2026-06-11', '2026-06-20'))
            ->assertViewHas('deltaPct', null);
        $this->actingAs($user)->get('/my-purchases?'.$this->period('2000-01-01', now()->toDateString()))
            ->assertViewHas('deltaPct', null);
    }

    public function test_index_daily_average_for_all_time_starts_at_the_first_note(): void
    {
        $user = User::factory()->create();
        $this->invoiceAt($user, now()->subDays(9)->toDateString(), 100);

        $this->actingAs($user)->get('/my-purchases?'.$this->period('2000-01-01', now()->toDateString()))
            ->assertViewHas('dailyAverage', 10.0); // 100 / 10 dias, não / ~9 mil
    }

    // ─── Exclusão ───────────────────────────────────────────────────────────────────────────

    public function test_destroy_redirects_unauthenticated_user(): void
    {
        $invoice = Invoice::factory()->create();

        $this->delete("/my-purchases/{$invoice->id}")->assertRedirect('/login');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_destroy_removes_note_with_items_payments_and_qr_reads_but_keeps_the_issuer(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'method' => '01', 'amount' => 10]);
        QrCodeRead::factory()->create(['user_id' => $user->id, 'invoice_id' => $invoice->id]);
        $failedRead = QrCodeRead::factory()->create(['user_id' => $user->id, 'invoice_id' => null]);

        $this->actingAs($user)
            ->delete("/my-purchases/{$invoice->id}")
            ->assertRedirect(route('my-purchases.index'))
            ->assertSessionHas('success', 'Nota fiscal excluída.');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoices_items', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseMissing('invoices_payments', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseMissing('qrcode_reads', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('qrcode_reads', ['id' => $failedRead->id]);
        $this->assertDatabaseHas('issuers', ['id' => $issuer->id]);
    }

    public function test_destroy_returns_404_and_keeps_note_of_another_user(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->delete("/my-purchases/{$invoice->id}")->assertNotFound();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_destroy_also_removes_pending_notes(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->pending()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete("/my-purchases/{$invoice->id}")->assertRedirect(route('my-purchases.index'));

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    // ─── Detalhe ───────────────────────────────────────────────────────────────────────────

    public function test_detail_links_to_the_issuer_and_offers_deletion(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(200)
            ->assertSee(route('issuers.detail', ['id' => $issuer->id]), false)
            ->assertSee('Ver emissor')
            ->assertSee(route('my-purchases.destroy', $invoice), false);
    }
}
