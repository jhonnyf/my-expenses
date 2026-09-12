<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

class NfceXmlRedactor
{
    private const NS = 'http://www.portalfiscal.inf.br/nfe';

    /**
     * Remove o CPF/CNPJ do destinatário (comprador) de um XML de NFC-e antes
     * de persistir o documento bruto, já que esse campo não tem uso conhecido
     * no sistema além de auditoria e não deve reter dado pessoal indefinidamente.
     */
    public static function redact(string $xml): string
    {
        if (trim($xml) === '') {
            return $xml;
        }

        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();

        if (! $loaded) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('nfe', self::NS);

        $nodes = $xpath->query('//nfe:dest/nfe:CPF | //nfe:dest/nfe:CNPJ');

        if ($nodes === false || $nodes->length === 0) {
            return $xml;
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }

        return $dom->saveXML() ?: $xml;
    }
}
