<?php

namespace Tests\Unit\Services;

use App\Services\NFCeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NFCeServiceTest extends TestCase
{
    private NFCeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NFCeService::class);
    }

    // Chave de exemplo: 35 2606 00000000000191 65 001 000001234 1 23456789 0
    // Pos: 0-1=cUF, 2-5=AAMM, 6-19=CNPJ, 20-21=mod, 22-24=serie, 25-33=nNF, 34=tpEmis, 35-43=cNF, 44=cDV
    private string $sampleKey = '35260600000000000191650010000012341234567890';

    public function test_is_contingencia_is_false_for_normal_emission(): void
    {
        $this->assertFalse($this->service->isContingencia($this->sampleKey));
    }

    public function test_is_contingencia_is_true_for_offline_contingency(): void
    {
        $offlineKey = substr_replace($this->sampleKey, '9', 34, 1);

        $this->assertTrue($this->service->isContingencia($offlineKey));
    }

    public function test_extrair_serie_returns_3_digit_series(): void
    {
        $this->assertSame('001', $this->service->extrairSerie($this->sampleKey));
    }

    public function test_dados_provisorios_do_qr_reads_value_and_day_from_offline_qr(): void
    {
        $url = "https://nfce.exemplo.gov.br/consulta?p={$this->sampleKey}|2|1|15|45.90|abcdef|000001|HASH";

        $dados = $this->service->dadosProvisoriosDoQr($url);

        $this->assertSame('2026-06-15', $dados['emitido_em']);
        $this->assertSame(45.90, $dados['valor_nota']);
    }

    public function test_dados_provisorios_do_qr_is_empty_for_online_qr(): void
    {
        $url = "https://nfce.exemplo.gov.br/consulta?p={$this->sampleKey}|2|1|000001|HASH";

        $this->assertSame([], $this->service->dadosProvisoriosDoQr($url));
    }

    public function test_dados_provisorios_do_qr_is_empty_without_p_param(): void
    {
        $this->assertSame([], $this->service->dadosProvisoriosDoQr("https://nfce.exemplo.gov.br/consulta?chNFe={$this->sampleKey}"));
    }

    public function test_extrair_uf_returns_state_code(): void
    {
        $uf = $this->service->extrairUF($this->sampleKey);

        $this->assertEquals('SP', $uf);
    }

    public function test_extrair_cnpj_returns_14_digit_cnpj(): void
    {
        $cnpj = $this->service->extrairCNPJ($this->sampleKey);

        $this->assertEquals('00000000000191', $cnpj);
        $this->assertSame(14, strlen($cnpj));
    }

    public function test_extrair_numero_nota_returns_9_digit_number(): void
    {
        $numero = $this->service->extrairNumeroNota($this->sampleKey);

        $this->assertSame(9, strlen($numero));
    }

    public function test_extrair_chave_de_url_returns_null_for_invalid_url(): void
    {
        $result = $this->service->extrairChaveDeUrl('https://example.com/nfce?token=abc');

        $this->assertNull($result);
    }

    public function test_extrair_chave_de_url_extracts_from_ch_n_fe_param(): void
    {
        $key = str_repeat('1', 44);
        $url = 'https://nfce.sefaz.sp.gov.br/consulta?chNFe='.$key;

        $result = $this->service->extrairChaveDeUrl($url);

        $this->assertEquals($key, $result);
    }

    public function test_extrair_chave_de_url_extracts_from_p_param(): void
    {
        $key = str_repeat('2', 44);
        $url = 'https://nfce.sefaz.sp.gov.br/consulta?p='.$key.'|10|1|abc';

        $result = $this->service->extrairChaveDeUrl($url);

        $this->assertEquals($key, $result);
    }

    public function test_consultar_por_qr_code_parses_html_returned_directly(): void
    {
        $url = 'https://nfce.exemplo.gov.br/consulta?p='.$this->sampleKey.'|10|1|abc';

        Http::fake([
            'nfce.exemplo.gov.br/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html')),
                200
            ),
        ]);

        $resultado = $this->service->consultarPorQRCode($url);

        $this->assertSame('MERCADO EXEMPLO LTDA', $resultado['dados']['emitente']['nome']);
        $this->assertSame('12345678000190', $resultado['dados']['emitente']['cnpj']);
        $this->assertCount(1, $resultado['dados']['itens']);
        $this->assertSame('PRODUTO TESTE UM', $resultado['dados']['itens'][0]['descricao']);
        $this->assertSame(10.00, $resultado['dados']['totais']['valor_produtos']);

        Http::assertSentCount(1);
    }

    public function test_consultar_por_qr_code_separates_valor_produtos_from_valor_nota_when_ha_desconto(): void
    {
        $url = 'https://nfce.exemplo.gov.br/consulta?p='.$this->sampleKey.'|10|1|abc';

        Http::fake([
            'nfce.exemplo.gov.br/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/nfce_portal_direto_com_desconto.html')),
                200
            ),
        ]);

        $resultado = $this->service->consultarPorQRCode($url);

        // "Valor total R$" (soma dos itens, sem desconto) e "Valor a pagar R$" (com
        // desconto aplicado) são linhas distintas do DANFE — valor_produtos deve bater
        // com o item (3,59), não com o valor final pago (2,99).
        $this->assertSame(3.59, $resultado['dados']['totais']['valor_produtos']);
        $this->assertSame(0.60, $resultado['dados']['totais']['valor_desconto']);
        $this->assertSame(2.99, $resultado['dados']['totais']['valor_nota']);
    }

    public function test_consultar_por_qr_code_resolves_content_embedded_behind_iframe(): void
    {
        $url = 'https://nfeweb.exemplo.gov.br/nfeweb/sites/nfce/danfeNFCe?p='.$this->sampleKey.'|10|1|abc';

        Http::fake([
            'nfeweb.exemplo.gov.br/nfeweb/sites/nfce/render/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/nfce_portal_iframe_render.html')),
                200
            ),
            'nfeweb.exemplo.gov.br/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/nfce_portal_iframe_outer.html')),
                200
            ),
        ]);

        $resultado = $this->service->consultarPorQRCode($url);

        $this->assertSame('MERCADO EXEMPLO LTDA', $resultado['dados']['emitente']['nome']);
        $this->assertCount(1, $resultado['dados']['itens']);
        $this->assertSame('PRODUTO TESTE UM', $resultado['dados']['itens'][0]['descricao']);
        $this->assertSame(10.00, $resultado['dados']['totais']['valor_produtos']);

        Http::assertSentCount(2);
    }

    public function test_consultar_por_qr_code_throws_when_iframe_content_cannot_be_fetched(): void
    {
        $url = 'https://nfeweb.exemplo.gov.br/nfeweb/sites/nfce/danfeNFCe?p='.$this->sampleKey.'|10|1|abc';

        Http::fake([
            'nfeweb.exemplo.gov.br/nfeweb/sites/nfce/render/*' => Http::response('erro interno', 500),
            'nfeweb.exemplo.gov.br/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/nfce_portal_iframe_outer.html')),
                200
            ),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->consultarPorQRCode($url);
    }
}
