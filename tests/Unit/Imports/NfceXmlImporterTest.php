<?php

namespace Tests\Unit\Imports;

use App\Imports\NfceXmlImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NfceXmlImporterTest extends TestCase
{
    public function test_parses_fixture_removing_leading_zeros_from_cep_and_normalizing_text()
    {
        $parsed = (new NfceXmlImporter)->fromFile(base_path('tests/fixtures/nfce.xml'));

        $this->assertSame('1310100', $parsed['emitente']['cep']);
        $this->assertSame('Supermercado fixture ltda', $parsed['emitente']['nome']);
        $this->assertSame('Rua das flores', $parsed['emitente']['logradouro']);
        $this->assertSame('Centro', $parsed['emitente']['bairro']);
        $this->assertSame('Sao paulo', $parsed['emitente']['municipio']);
        $this->assertSame('SP', $parsed['emitente']['uf']);
        $this->assertSame('Produto fixture teste', $parsed['itens'][0]['descricao']);
    }

    #[DataProvider('cepProvider')]
    public function test_normalizes_cep_leading_zeros(string $xmlCep, string $expected)
    {
        $xml = $this->fixtureWithCep($xmlCep);

        $parsed = (new NfceXmlImporter)->fromString($xml);

        $this->assertSame($expected, $parsed['emitente']['cep']);
    }

    public static function cepProvider(): array
    {
        return [
            'multiple leading zeros' => ['000534', '534'],
            'single leading zero' => ['01310100', '1310100'],
            'no leading zeros' => ['87654321', '87654321'],
            'all zeros' => ['00000000', '0'],
        ];
    }

    #[DataProvider('textProvider')]
    public function test_normalizes_text_case(string $input, string $expected)
    {
        $xml = str_replace('SUPERMERCADO FIXTURE LTDA', $input, $this->fixtureXml());

        $parsed = (new NfceXmlImporter)->fromString($xml);

        $this->assertSame($expected, $parsed['emitente']['nome']);
    }

    public static function textProvider(): array
    {
        return [
            'all caps' => ['SUPERMERCADO BOM PRECO', 'Supermercado bom preco'],
            'all lower' => ['supermercado bom preco', 'Supermercado bom preco'],
            'mixed case' => ['SuperMercado Bom Preco', 'Supermercado bom preco'],
        ];
    }

    private function fixtureXml(): string
    {
        return file_get_contents(base_path('tests/fixtures/nfce.xml'));
    }

    private function fixtureWithCep(string $cep): string
    {
        return preg_replace('/<CEP>.*<\/CEP>/', "<CEP>{$cep}</CEP>", $this->fixtureXml());
    }
}
