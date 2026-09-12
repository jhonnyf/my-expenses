<?php

namespace Tests\Unit\Support;

use App\Support\NfceXmlRedactor;
use Tests\TestCase;

class NfceXmlRedactorTest extends TestCase
{
    public function test_removes_cpf_from_destinatario(): void
    {
        $xml = file_get_contents(base_path('tests/fixtures/nfce.xml'));

        $redacted = NfceXmlRedactor::redact($xml);

        $this->assertStringContainsString('11122233344', $xml);
        $this->assertStringNotContainsString('11122233344', $redacted);
        $this->assertStringContainsString('<xNome>CONSUMIDOR FIXTURE</xNome>', $redacted);
    }

    public function test_removes_cnpj_from_destinatario(): void
    {
        $xml = str_replace('<CPF>11122233344</CPF>', '<CNPJ>11222333000181</CNPJ>', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $redacted = NfceXmlRedactor::redact($xml);

        $this->assertStringNotContainsString('11222333000181', $redacted);
    }

    public function test_returns_original_xml_when_no_destinatario_document_present(): void
    {
        $xml = str_replace('<CPF>11122233344</CPF>', '', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $redacted = NfceXmlRedactor::redact($xml);

        $this->assertSame($xml, $redacted);
    }

    public function test_returns_input_unchanged_when_xml_is_invalid(): void
    {
        $invalid = '<not-valid-xml>';

        $this->assertSame($invalid, NfceXmlRedactor::redact($invalid));
    }
}
