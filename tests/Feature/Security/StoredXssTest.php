<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\IssuerNickname;
use App\Models\User;
use App\Services\NFCeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nome de mercado, endereço, descrição e unidade vêm de notas fiscais (de qualquer usuário). Nenhuma tela
 * pode devolvê-los como HTML: o Blade escapa e o front usa Utils.escapeHtml (ver FrontendEscapingTest).
 */
class StoredXssTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private User $user;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->pro()->create();
        $issuer = Issuer::factory()->create([
            'name' => 'LOJA '.self::PAYLOAD,
            'street' => 'RUA '.self::PAYLOAD,
            'neighborhood' => 'BAIRRO '.self::PAYLOAD,
            'city' => 'CIDADE '.self::PAYLOAD,
            'state' => 'GO',
        ]);
        $this->invoice = Invoice::factory()->for($this->user)->create(['issuer_id' => $issuer->id, 'issued_at' => now()]);
        InvoiceItem::factory()->for($this->invoice)->create([
            'description' => 'ITEM '.self::PAYLOAD,
            'unit' => 'UN'.self::PAYLOAD,
            'unit_price' => 5,
        ]);
    }

    private function assertEscaped($response): void
    {
        $response->assertOk()->assertDontSee(self::PAYLOAD, false);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->getContent());
    }

    public function test_server_rendered_pages_escape_market_and_product_text(): void
    {
        $this->actingAs($this->user);
        IssuerNickname::create(['user_id' => $this->user->id, 'issuer_id' => $this->invoice->issuer_id, 'nickname' => 'APELIDO '.self::PAYLOAD]);

        $this->assertEscaped($this->get(route('my-purchases.detail', $this->invoice)));
        $this->assertEscaped($this->get(route('my-purchases.index', ['start_date' => '2000-01-01'])));
        $this->assertEscaped($this->get(route('issuers.index')));
        $this->assertEscaped($this->get(route('issuers.detail', ['id' => $this->invoice->issuer_id])));
        $this->assertEscaped($this->get(route('reports.index', ['start_date' => '2000-01-01'])));
        $this->assertEscaped($this->get(route('categories.uncategorized', ['start_date' => '2000-01-01'])));
        $this->assertEscaped($this->get(route('dashboard.index', ['start_date' => '2000-01-01'])));
    }

    public function test_json_endpoints_keep_the_raw_text_so_the_front_end_escapes_it_once(): void
    {
        // O JSON não escapa HTML (o front usa Utils.escapeHtml); o que importa é o Content-Type não ser HTML.
        $response = $this->actingAs($this->user)->getJson(route('shopping-list.search', ['q' => 'ITEM', 'city' => 'CIDADE '.self::PAYLOAD, 'state' => 'GO']));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('onerror', $response->getContent());
    }

    public function test_scraped_unit_only_keeps_characters_a_commercial_unit_can_have(): void
    {
        $key = '35260612345678000190650010000012341234567890';
        $html = file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html'));
        $html = preg_replace('/(RUN"><strong>UN: <\/strong>)[^<]*/', '${1}"onmouseover=alert(1)', $html, 1, $count);
        $this->assertSame(1, $count, 'a fixture mudou de formato');
        Http::fake(['*' => Http::response($html, 200)]);

        $itens = app(NFCeService::class)->consultarPorQRCode("https://nfce.fazenda.sp.gov.br/consulta?p={$key}|2|1")['dados']['itens'];

        $this->assertStringNotContainsString('"', $itens[0]['unidade']);
        $this->assertStringNotContainsString('=', $itens[0]['unidade']);
    }

    public function test_scraped_unit_keeps_real_units(): void
    {
        $key = '35260612345678000190650010000012341234567890';
        $units = ['UN', 'KG', 'LT', 'M²', 'CX'];
        $sequence = Http::sequence();
        foreach ($units as $unit) {
            $sequence->push(preg_replace(
                '/(RUN"><strong>UN: <\/strong>)[^<]*/',
                '${1}'.$unit,
                file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html')),
                1
            ));
        }
        Http::fake(['*' => $sequence]);

        foreach ($units as $unit) {
            $itens = app(NFCeService::class)->consultarPorQRCode("https://nfce.fazenda.sp.gov.br/consulta?p={$key}|2|1")['dados']['itens'];

            $this->assertSame($unit, $itens[0]['unidade']);
        }
    }
}
