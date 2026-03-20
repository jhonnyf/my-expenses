<?php

namespace App\Imports;

use SimpleXMLElement;

class NfceXmlImporter
{
    private const NS = 'http://www.portalfiscal.inf.br/nfe';

    private const FORMAS_PAGAMENTO = [
        '01' => 'dinheiro',
        '02' => 'cheque',
        '03' => 'cartao_credito',
        '04' => 'cartao_debito',
        '05' => 'credito_loja',
        '10' => 'vale_alimentacao',
        '11' => 'vale_refeicao',
        '12' => 'vale_presente',
        '13' => 'vale_combustivel',
        '15' => 'boleto',
        '90' => 'sem_pagamento',
        '99' => 'outros',
    ];

    public function fromFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Arquivo não encontrado: {$path}");
        }

        return $this->fromString(file_get_contents($path));
    }

    public function fromString(string $xml): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);

        if ($root === false) {
            $errors = array_map(fn($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new \InvalidArgumentException('XML inválido: ' . implode('; ', $errors));
        }

        $root->registerXPathNamespace('nfe', self::NS);

        $infNFe = $root->xpath('//nfe:infNFe')[0] ?? null;

        if ($infNFe === null) {
            throw new \InvalidArgumentException('Estrutura de NFC-e não encontrada no XML.');
        }

        $infNFe->registerXPathNamespace('nfe', self::NS);

        // O atributo Id contém o prefixo "NFe" seguido dos 44 dígitos da chave de acesso
        $accessKey = preg_replace('/\D/', '', $this->attr($infNFe, 'Id'));

        return [
            'chave'      => $accessKey,
            'numero'     => $this->val($infNFe, 'nfe:ide/nfe:nNF'),
            'serie'      => $this->val($infNFe, 'nfe:ide/nfe:serie'),
            'emitido_em' => $this->val($infNFe, 'nfe:ide/nfe:dhEmi'),
            'ambiente'   => $this->val($infNFe, 'nfe:ide/nfe:tpAmb') === '1' ? 'producao' : 'homologacao',
            'emitente'   => $this->parseEmitente($infNFe),
            'destinatario' => $this->parseDestinatario($infNFe),
            'itens'      => $this->parseItens($infNFe),
            'total'      => $this->parseTotal($infNFe),
            'pagamento'  => $this->parsePagamento($infNFe),
        ];
    }

    private function parseEmitente(SimpleXMLElement $infNFe): array
    {
        return [
            'cnpj'       => $this->val($infNFe, 'nfe:emit/nfe:CNPJ'),
            'nome'       => $this->val($infNFe, 'nfe:emit/nfe:xNome'),
            'logradouro' => $this->val($infNFe, 'nfe:emit/nfe:enderEmit/nfe:xLgr'),
            'numero'     => $this->val($infNFe, 'nfe:emit/nfe:enderEmit/nfe:nro'),
            'bairro'     => $this->val($infNFe, 'nfe:emit/nfe:enderEmit/nfe:xBairro'),
            'municipio'  => $this->val($infNFe, 'nfe:emit/nfe:enderEmit/nfe:xMun'),
            'uf'         => $this->val($infNFe, 'nfe:emit/nfe:enderEmit/nfe:UF'),
            'cep'        => $this->val($infNFe, 'nfe:emit/nfe:enderEmit/nfe:CEP'),
        ];
    }

    private function parseDestinatario(SimpleXMLElement $infNFe): array
    {
        return [
            'cpf'  => $this->val($infNFe, 'nfe:dest/nfe:CPF'),
            'cnpj' => $this->val($infNFe, 'nfe:dest/nfe:CNPJ'),
            'nome' => $this->val($infNFe, 'nfe:dest/nfe:xNome'),
        ];
    }

    private function parseItens(SimpleXMLElement $infNFe): array
    {
        $itens = [];

        foreach ($infNFe->xpath('nfe:det') as $det) {
            $det->registerXPathNamespace('nfe', self::NS);

            $itens[] = [
                'numero_item'    => (int) $this->attr($det, 'nItem'),
                'codigo'         => $this->val($det, 'nfe:prod/nfe:cProd'),
                'descricao'      => $this->val($det, 'nfe:prod/nfe:xProd'),
                'ncm'            => $this->val($det, 'nfe:prod/nfe:NCM'),
                'cfop'           => $this->val($det, 'nfe:prod/nfe:CFOP'),
                'unidade'        => $this->val($det, 'nfe:prod/nfe:uCom'),
                'quantidade'     => (float) $this->val($det, 'nfe:prod/nfe:qCom'),
                'valor_unitario' => (float) $this->val($det, 'nfe:prod/nfe:vUnCom'),
                'valor_total'    => (float) $this->val($det, 'nfe:prod/nfe:vProd'),
            ];
        }

        return $itens;
    }

    private function parseTotal(SimpleXMLElement $infNFe): array
    {
        return [
            'base_calculo_icms' => (float) $this->val($infNFe, 'nfe:total/nfe:ICMSTot/nfe:vBC'),
            'valor_icms'        => (float) $this->val($infNFe, 'nfe:total/nfe:ICMSTot/nfe:vICMS'),
            'valor_produtos'    => (float) $this->val($infNFe, 'nfe:total/nfe:ICMSTot/nfe:vProd'),
            'valor_nota'        => (float) $this->val($infNFe, 'nfe:total/nfe:ICMSTot/nfe:vNF'),
            'valor_tributos'    => (float) $this->val($infNFe, 'nfe:total/nfe:ICMSTot/nfe:vTotTrib'),
        ];
    }

    private function parsePagamento(SimpleXMLElement $infNFe): array
    {
        $pagamentos = [];

        foreach ($infNFe->xpath('nfe:pag/nfe:detPag') as $detPag) {
            $detPag->registerXPathNamespace('nfe', self::NS);

            $tPag = $this->val($detPag, 'nfe:tPag');

            $pagamentos[] = [
                'forma' => self::FORMAS_PAGAMENTO[$tPag] ?? 'outros',
                'valor' => (float) $this->val($detPag, 'nfe:vPag'),
            ];
        }

        return $pagamentos;
    }

    private function val(SimpleXMLElement $node, string $xpath): string
    {
        $result = $node->xpath($xpath);

        return isset($result[0]) ? (string) $result[0] : '';
    }

    private function attr(SimpleXMLElement $node, string $attribute): string
    {
        return isset($node[$attribute]) ? (string) $node[$attribute] : '';
    }
}
