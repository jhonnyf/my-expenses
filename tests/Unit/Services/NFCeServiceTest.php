<?php

namespace Tests\Unit\Services;

use App\Services\NFCeService;
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

    public function test_extrair_chave_de_url_extracts_from_chNFe_param(): void
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
}
