<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\User;
use App\Services\NFCeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    /** O log do QR guarda só host, caminho e o início da chave (ver LogQrCodeReadAction). */
    private function redactedQrUrl(string $url): string
    {
        return preg_replace_callback('/\?p=(\d{44})\|.*$/', fn ($m) => '?chave='.substr($m[1], 0, 20).'…', $url);
    }

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/invoices')->assertStatus(401);
    }

    public function test_index_returns_paginated_invoices_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(3)->for($user)->create();
        Invoice::factory()->count(2)->create(); // de outro usuário

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_period_when_start_and_end_date_given(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->for($user)->create(['issued_at' => '2026-01-15 10:00:00']);
        Invoice::factory()->for($user)->create(['issued_at' => '2026-03-10 10:00:00']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices?start_date=2026-01-01&end_date=2026-01-31')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.issued_at', '2026-01-15T10:00:00.000000Z');
    }

    public function test_index_returns_issuer_nickname_as_display_name_when_set(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Razão Social Oficial LTDA']);
        IssuerNickname::factory()->for($user)->for($issuer)->create(['nickname' => 'Mercadinho da esquina']);
        Invoice::factory()->for($user)->for($issuer)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonPath('data.0.issuer.display_name', 'Mercadinho da esquina');
    }

    public function test_index_returns_issuer_name_as_display_name_when_no_nickname(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Razão Social Oficial LTDA']);
        Invoice::factory()->for($user)->for($issuer)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonPath('data.0.issuer.display_name', 'Razão Social Oficial LTDA');
    }

    public function test_show_returns_401_when_unauthenticated(): void
    {
        $invoice = Invoice::factory()->create();

        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertStatus(401);
    }

    public function test_show_returns_404_when_invoice_belongs_to_another_user(): void
    {
        $invoice = Invoice::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(404);
    }

    public function test_show_returns_invoice_for_owner(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonMissingPath('data.raw_xml');
    }

    public function test_import_xml_returns_401_when_unauthenticated(): void
    {
        $file = UploadedFile::fake()->createWithContent('nfce.xml', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $this->postJson('/api/v1/invoices/import/xml', ['xml' => $file])->assertStatus(401);
    }

    public function test_import_xml_creates_invoice(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('nfce.xml', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/xml', ['xml' => $file])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'access_key', 'total_amount']]);

        $this->assertDatabaseHas('invoices', ['user_id' => $user->id]);
        $this->assertDatabaseHas('issuers', ['cnpj' => '00000000000191']);

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $this->assertStringNotContainsString('11122233344', $invoice->raw_xml);
    }

    public function test_import_xml_returns_409_for_duplicate_invoice(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['cnpj' => '00000000000191']);
        Invoice::factory()->for($user)->for($issuer)->create([
            'access_key' => '35260600000000000191650010000012341234567890',
        ]);

        $file = UploadedFile::fake()->createWithContent('nfce.xml', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/xml', ['xml' => $file])
            ->assertStatus(409);
    }

    // ─── importByQrCode ──────────────────────────────────────────────────────

    public function test_import_by_qr_code_creates_invoice_and_logs_success(): void
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

        $qrcodeUrl = "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1";

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $qrcodeUrl]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $response->assertStatus(201);
        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'qrcode_url' => $this->redactedQrUrl($qrcodeUrl),
            'status' => 'success',
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_import_by_qr_code_returns_422_and_logs_error_when_key_not_found(): void
    {
        $user = User::factory()->create();

        $this->mock(NFCeService::class, function ($mock) {
            $mock->shouldReceive('extrairChaveDeUrl')->once()->andReturn(null);
        });

        $qrcodeUrl = 'https://www.nfce.fazenda.sp.gov.br/invalido';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $qrcodeUrl]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'qrcode_url' => $this->redactedQrUrl($qrcodeUrl),
            'status' => 'error',
            'error_message' => 'Não foi possível extrair a chave de acesso da URL.',
            'invoice_id' => null,
        ]);
    }

    public function test_import_by_qr_code_returns_409_and_logs_error_for_duplicate_invoice(): void
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

        $qrcodeUrl = "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1";

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $qrcodeUrl]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'qrcode_url' => $this->redactedQrUrl($qrcodeUrl),
            'status' => 'error',
            'error_message' => 'Esta nota fiscal já foi importada anteriormente.',
            'invoice_id' => null,
        ]);
    }

    // ─── Lista: busca, filtros, ordenação e período ───────────────────────────────────────────

    public function test_index_searches_by_nickname_number_and_issuer_filter(): void
    {
        $user = User::factory()->create();
        $issuerA = Issuer::factory()->create(['name' => 'Atacadao Central']);
        $issuerB = Issuer::factory()->create(['name' => 'Padaria Doce']);
        IssuerNickname::create(['user_id' => $user->id, 'issuer_id' => $issuerB->id, 'nickname' => 'Pao Quente']);
        $a = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuerA->id, 'number' => '111111']);
        $b = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuerB->id, 'number' => '222222']);

        $ids = fn (string $query) => collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices?'.$query)->assertStatus(200)->json('data'))->pluck('id')->all();

        $this->assertSame([$b->id], $ids('search=pao'));
        $this->assertSame([$a->id], $ids('search=111111'));
        $this->assertSame([$b->id], $ids('issuer_id='.$issuerB->id));
    }

    public function test_index_filters_by_status_and_sorts_by_value(): void
    {
        $user = User::factory()->create();
        $cheap = Invoice::factory()->create(['user_id' => $user->id, 'total_amount' => 10]);
        $pricey = Invoice::factory()->create(['user_id' => $user->id, 'total_amount' => 500]);
        $pending = Invoice::factory()->pending()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices?status=pending')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->id);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices?status=authorized&sort=highest')
            ->assertJsonPath('data.0.id', $pricey->id)->assertJsonPath('data.1.id', $cheap->id);
    }

    public function test_index_with_only_start_date_covers_until_today(): void
    {
        $user = User::factory()->create();
        $old = Invoice::factory()->create(['user_id' => $user->id, 'issued_at' => now()->subDays(40)]);
        $recent = Invoice::factory()->create(['user_id' => $user->id, 'issued_at' => now()->subDays(2)]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices?start_date='.now()->subDays(10)->toDateString())
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recent->id);
    }

    public function test_index_exposes_items_count_and_rejects_invalid_params(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);
        InvoiceItem::factory()->count(3)->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices')->assertJsonPath('data.0.items_count', 3);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices?sort=nope')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices?start_date=2026-06-10&end_date=2026-06-01')->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_index_search_never_reveals_other_users_notes(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['name' => 'Segredo Ltda']);
        Invoice::factory()->create(['user_id' => User::factory()->create()->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/invoices?search=Segredo')->assertJsonCount(0, 'data');
    }

    // ─── Exclusão ───────────────────────────────────────────────────────────────────────────

    public function test_destroy_returns_401_when_unauthenticated(): void
    {
        $invoice = Invoice::factory()->create();

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")->assertStatus(401);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_destroy_deletes_own_invoice_with_its_items(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/invoices/{$invoice->id}")->assertStatus(200);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoices_items', ['invoice_id' => $invoice->id]);
    }

    public function test_destroy_returns_404_for_invoice_of_another_user(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/invoices/{$invoice->id}")->assertStatus(404);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }
}
