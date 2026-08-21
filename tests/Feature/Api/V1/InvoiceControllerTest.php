<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
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

    public function test_show_returns_403_when_invoice_belongs_to_another_user(): void
    {
        $invoice = Invoice::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(403);
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
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => [], 'html' => '']);
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
            'qrcode_url' => $qrcodeUrl,
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
            'qrcode_url' => $qrcodeUrl,
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
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => [], 'html' => '']);
            $mock->shouldReceive('normalizarDadosPortal')->once()->andReturn(['chave' => $chave]);
        });

        $qrcodeUrl = "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1";

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/qrcode', ['qrcode_url' => $qrcodeUrl]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('qrcode_reads', [
            'user_id' => $user->id,
            'qrcode_url' => $qrcodeUrl,
            'status' => 'error',
            'error_message' => 'Esta nota fiscal já foi importada anteriormente.',
            'invoice_id' => null,
        ]);
    }
}
