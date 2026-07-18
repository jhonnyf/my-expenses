<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Services\NFCeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id]);

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

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $market->id]);
        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $pharmacy->id]);

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
            'issued_at' => now()->subYear(),
        ]);

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200)
            ->assertViewHas('totalCount', 2)
            ->assertViewHas('totalAmount', 150.0)
            ->assertViewHas('monthAmount', 100.0)
            ->assertViewHas('averageTicket', 75.0);
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

    public function test_detail_returns_403_for_invoice_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(403);
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
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => [], 'html' => '']);
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
            $mock->shouldReceive('consultarPorQRCode')->once()->andReturn(['dados' => [], 'html' => '']);
            $mock->shouldReceive('normalizarDadosPortal')->once()->andReturn(['chave' => $chave]);
        });

        $response = $this->actingAs($user)->postJson(route('my-purchases.import-qrcode'), [
            'qrcode_url' => "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1",
        ]);

        $response->assertStatus(422)->assertExactJson([
            'errors' => ['qrcode_url' => ['Esta nota fiscal já foi importada anteriormente.']],
        ]);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_import_by_qr_code_still_redirects_for_non_json_requests(): void
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

        $response = $this->actingAs($user)->post(route('my-purchases.import-qrcode'), [
            'qrcode_url' => "https://www.nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p={$chave}|3|1",
        ]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $response->assertRedirect(route('my-purchases.detail', $invoice->id));
    }
}
