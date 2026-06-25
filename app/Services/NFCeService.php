<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use NFePHP\Common\Certificate;
use NFePHP\Common\UFList;
use NFePHP\NFe\Tools;

class NFCeService
{
    private ?Tools $tools = null;

    private function getTools(): Tools
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $pfxPath = config('nfe.certificado_path');
        $pfxPassword = config('nfe.certificado_senha');
        $cnpj = config('nfe.cnpj');
        $razao = config('nfe.razao_social');
        $uf = config('nfe.uf');
        $tpAmb = (int) config('nfe.ambiente', 2);

        if (! file_exists($pfxPath)) {
            throw new \RuntimeException("Certificado não encontrado em: {$pfxPath}");
        }

        $certificate = Certificate::readPfx(file_get_contents($pfxPath), $pfxPassword);

        $configJson = json_encode([
            'atualizacao' => now()->format('Y-m-d H:i:s'),
            'tpAmb' => $tpAmb,
            'razaosocial' => $razao,
            'cnpj' => $cnpj,
            'siglaUF' => $uf,
            'schemes' => realpath(base_path('vendor/nfephp-org/sped-nfe/schemes')),
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => config('nfe.csc', ''),
            'CSCid' => config('nfe.csc_id', ''),
        ]);

        $this->tools = new Tools($configJson, $certificate);
        $this->tools->model('65'); // 55 = NF-e | 65 = NFC-e

        return $this->tools;
    }

    /**
     * Consulta a NF-e/NFC-e na SEFAZ pela chave de acesso e retorna os dados parseados do XML.
     */
    public function consultarPorChave(string $key): array
    {
        $xmlResponse = $this->getTools()->sefazConsultaChave($key);

        return $this->parseXmlResponse($xmlResponse);
    }

    /**
     * Extrai os dados relevantes do XML de resposta do SEFAZ.
     */
    private function parseXmlResponse(string $xml): array
    {
        $dom = new \DOMDocument;
        $dom->loadXML($xml);

        $result = [];

        // Status da consulta
        $cStat = $dom->getElementsByTagName('cStat')->item(0);
        $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0);

        $result['status'] = [
            'codigo' => $cStat?->nodeValue,
            'descricao' => $xMotivo?->nodeValue,
        ];

        // Protocolo de autorização
        $nProt = $dom->getElementsByTagName('nProt')->item(0);
        $dhRecbto = $dom->getElementsByTagName('dhRecbto')->item(0);
        if ($nProt) {
            $result['protocolo'] = [
                'numero' => $nProt->nodeValue,
                'data_recebimento' => $dhRecbto?->nodeValue,
            ];
        }

        // Dados da NF-e (se autorizada)
        $infNFe = $dom->getElementsByTagName('infNFe')->item(0);
        if ($infNFe) {
            // Emitente
            $emit = $dom->getElementsByTagName('emit')->item(0);
            if ($emit) {
                $result['emitente'] = [
                    'cnpj' => $this->getTagValue($emit, 'CNPJ'),
                    'razao_social' => $this->getTagValue($emit, 'xNome'),
                    'fantasia' => $this->getTagValue($emit, 'xFant'),
                    'ie' => $this->getTagValue($emit, 'IE'),
                    'uf' => $this->getTagValue($emit, 'UF'),
                    'municipio' => $this->getTagValue($emit, 'xMun'),
                ];
            }

            // Destinatário
            $dest = $dom->getElementsByTagName('dest')->item(0);
            if ($dest) {
                $result['destinatario'] = [
                    'cnpj_cpf' => $this->getTagValue($dest, 'CNPJ') ?: $this->getTagValue($dest, 'CPF'),
                    'razao_social' => $this->getTagValue($dest, 'xNome'),
                    'uf' => $this->getTagValue($dest, 'UF'),
                ];
            }

            // Totais
            $total = $dom->getElementsByTagName('ICMSTot')->item(0);
            if ($total) {
                $result['totais'] = [
                    'valor_produtos' => $this->getTagValue($total, 'vProd'),
                    'valor_desconto' => $this->getTagValue($total, 'vDesc'),
                    'valor_frete' => $this->getTagValue($total, 'vFrete'),
                    'valor_total' => $this->getTagValue($total, 'vNF'),
                    'valor_icms' => $this->getTagValue($total, 'vICMS'),
                ];
            }

            // Itens
            $items = [];
            foreach ($dom->getElementsByTagName('det') as $det) {
                $prod = $det->getElementsByTagName('prod')->item(0);
                if ($prod) {
                    $items[] = [
                        'item' => $det->getAttribute('nItem'),
                        'codigo' => $this->getTagValue($prod, 'cProd'),
                        'descricao' => $this->getTagValue($prod, 'xProd'),
                        'ncm' => $this->getTagValue($prod, 'NCM'),
                        'quantidade' => $this->getTagValue($prod, 'qCom'),
                        'unidade' => $this->getTagValue($prod, 'uCom'),
                        'valor_unit' => $this->getTagValue($prod, 'vUnCom'),
                        'valor_total' => $this->getTagValue($prod, 'vProd'),
                    ];
                }
            }
            $result['itens'] = $items;

            // Pagamentos
            $pagamentos = [];
            foreach ($dom->getElementsByTagName('detPag') as $pag) {
                $pagamentos[] = [
                    'tipo' => $this->getTagValue($pag, 'tPag'),
                    'valor' => $this->getTagValue($pag, 'vPag'),
                ];
            }
            $result['pagamentos'] = $pagamentos;
        }

        return $result;
    }

    private function getTagValue(\DOMElement $parent, string $tag): ?string
    {
        $node = $parent->getElementsByTagName($tag)->item(0);

        return $node?->nodeValue;
    }

    public function extrairChaveDeUrl(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY) ?? '';
        $decodedQuery = urldecode($query);

        if (preg_match('/chNFe=(\d{44})/', $decodedQuery, $m)) {
            return $m[1];
        }

        if (preg_match('/[?&]p=(\d{44})\|/', $decodedQuery, $m)) {
            return $m[1];
        }

        if (preg_match('/(\d{44})/', $decodedQuery, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array{dados: array, html: string}
     *
     * @throws \InvalidArgumentException Se a URL não for de um domínio SEFAZ válido.
     * @throws \RuntimeException Se a consulta falhar.
     */
    public function consultarPorQRCode(string $url): array
    {
        $this->validarUrlSefaz($url);

        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; NFCe-Reader/1.0)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Erro ao consultar portal SEFAZ: HTTP {$response->status()}");
        }

        $html = $response->body();

        return [
            'dados' => $this->parseHtmlPortal($html),
            'html' => $html,
        ];
    }

    /**
     * Valida que a URL pertence a um domínio SEFAZ oficial para prevenir SSRF.
     */
    private function validarUrlSefaz(string $url): void
    {
        $dominiosPermitidos = [
            'sefaz.', 'sefaznet.', 'sefin.', 'sefa.', 'set.',
            'fazenda.', 'fazenda.sp.gov.br', 'fazenda.rj.gov.br',
            'fazenda.pr.gov.br', 'fazenda.df.gov.br', 'fazenda.mg.gov.br',
            'nfce.fazenda.sp.gov.br', 'homologacao.nfce.fazenda.sp.gov.br',
            'nfce.se.gov.br', 'hom.nfe.se.gov.br',
            'sat.sef.sc.gov.br', 'hom.sat.sef.sc.gov.br',
            'portalsped.fazenda.mg.gov.br', 'hportalsped.fazenda.mg.gov.br',
            'dfe.ms.gov.br', 'nfce.sefaz.pe.gov.br',
        ];

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            throw new \InvalidArgumentException('URL inválida.');
        }

        foreach ($dominiosPermitidos as $dominio) {
            if (str_contains($host, $dominio)) {
                return;
            }
        }

        // Aceita qualquer subdomínio de *.gov.br como fallback
        if (str_ends_with($host, '.gov.br')) {
            return;
        }

        throw new \InvalidArgumentException("URL não pertence a um domínio SEFAZ reconhecido: {$host}");
    }

    private function parseHtmlPortal(string $html): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        return [
            'chave' => $this->extrairChaveDoHtml($xpath),
            'emitente' => $this->extrairEmitenteDoHtml($xpath),
            'itens' => $this->extrairItensDoHtml($xpath),
            'totais' => $this->extrairTotaisDoHtml($xpath),
            'pagamento' => $this->extrairPagamentoDoHtml($xpath),
            'metadados' => $this->extrairMetadadosDoHtml($xpath),
        ];
    }

    private function extrairChaveDoHtml(\DOMXPath $xpath): string
    {
        $node = $xpath->query("//*[contains(@class,'chave')]")->item(0);
        if ($node) {
            return preg_replace('/\D/', '', $node->textContent);
        }

        return '';
    }

    private function extrairEmitenteDoHtml(\DOMXPath $xpath): array
    {
        $nome = '';
        $cnpj = '';
        $endereco = '';

        $nomeNode = $xpath->query("//*[@id='u20']")->item(0)
            ?? $xpath->query("//*[contains(@class,'txtTopo')]")->item(0);
        if ($nomeNode) {
            $nome = trim($nomeNode->textContent);
        }

        $textDivs = $xpath->query("//div[contains(@class,'txtCenter')]//div[contains(@class,'text')]");
        foreach ($textDivs as $div) {
            $text = trim($div->textContent);
            if (str_contains($text, 'CNPJ')) {
                $cnpj = preg_replace('/\D/', '', preg_replace('/.*CNPJ:\s*/', '', $text));
            } else {
                $endereco = $text;
            }
        }

        $endParts = $this->parsearEndereco($endereco);

        return [
            'cnpj' => $cnpj,
            'nome' => $nome,
            'logradouro' => $endParts['logradouro'],
            'numero' => $endParts['numero'],
            'bairro' => $endParts['bairro'],
            'municipio' => $endParts['municipio'],
            'uf' => $endParts['uf'],
            'cep' => $endParts['cep'],
        ];
    }

    private function parsearEndereco(string $endereco): array
    {
        $default = ['logradouro' => '', 'numero' => '', 'bairro' => '', 'municipio' => '', 'uf' => '', 'cep' => ''];

        if (empty($endereco)) {
            return $default;
        }

        // SP format: "RUA X , NUMERO , COMPLEMENTO , BAIRRO , CIDADE , UF"
        $parts = array_map('trim', explode(',', $endereco));
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

        $count = count($parts);

        return [
            'logradouro' => $parts[0] ?? '',
            'numero' => $count >= 2 ? $parts[1] : '',
            'bairro' => $count >= 4 ? $parts[$count - 3] : '',
            'municipio' => $count >= 3 ? $parts[$count - 2] : '',
            'uf' => $count >= 2 ? $parts[$count - 1] : '',
            'cep' => '',
        ];
    }

    private function extrairItensDoHtml(\DOMXPath $xpath): array
    {
        $itens = [];
        $rows = $xpath->query("//*[@id='tabResult']//tr[starts-with(@id,'Item')]");

        foreach ($rows as $index => $row) {
            $descNode = $xpath->query(".//span[contains(@class,'txtTit')]", $row)->item(0);
            $codNode = $xpath->query(".//span[contains(@class,'RCod')]", $row)->item(0);
            $qtdNode = $xpath->query(".//span[contains(@class,'Rqtd')]", $row)->item(0);
            $unNode = $xpath->query(".//span[contains(@class,'RUN')]", $row)->item(0);
            $vlUnitNode = $xpath->query(".//span[contains(@class,'RvlUnit')]", $row)->item(0);
            $vlTotalNode = $xpath->query(".//span[contains(@class,'valor')]", $row)->item(0);

            $codigo = '';
            if ($codNode) {
                preg_match('/Código:\s*([\w.-]+)/', $codNode->textContent, $m);
                $codigo = $m[1] ?? '';
            }

            $quantidade = '';
            if ($qtdNode) {
                preg_match('/Qtde\.?:\s*([\d.,]+)/', $qtdNode->textContent, $m);
                $quantidade = $m[1] ?? '';
            }

            $unidade = '';
            if ($unNode) {
                preg_match('/UN:\s*(\S+)/', $unNode->textContent, $m);
                $unidade = $m[1] ?? '';
            }

            $valorUnitario = '';
            if ($vlUnitNode) {
                preg_match('/Vl\.\s*Unit\.?:\s*([\d.,]+)/', $vlUnitNode->textContent, $m);
                $valorUnitario = $m[1] ?? '';
            }

            $itens[] = [
                'numero_item' => $index + 1,
                'codigo' => trim($codigo),
                'descricao' => $descNode ? trim($descNode->textContent) : '',
                'unidade' => trim($unidade),
                'quantidade' => $this->parseBrDecimal($quantidade),
                'valor_unitario' => $this->parseBrDecimal($valorUnitario),
                'valor_total' => $vlTotalNode ? $this->parseBrDecimal(trim($vlTotalNode->textContent)) : 0.0,
            ];
        }

        return $itens;
    }

    private function extrairTotaisDoHtml(\DOMXPath $xpath): array
    {
        $valorTotal = 0.0;
        $valorTributos = 0.0;

        $totalNode = $xpath->query("//*[@id='totalNota']//span[contains(@class,'txtMax')]")->item(0);
        if ($totalNode) {
            $valorTotal = $this->parseBrDecimal(trim($totalNode->textContent));
        }

        $tribNode = $xpath->query("//*[@id='totalNota']//span[contains(@class,'txtObs')]")->item(0);
        if ($tribNode) {
            $valorTributos = $this->parseBrDecimal(trim($tribNode->textContent));
        }

        return [
            'valor_produtos' => $valorTotal,
            'valor_nota' => $valorTotal,
            'valor_tributos' => $valorTributos,
        ];
    }

    private function extrairPagamentoDoHtml(\DOMXPath $xpath): array
    {
        $pagamentos = [];
        $formasPagamentoMap = [
            'dinheiro' => 'dinheiro',
            'cartão de crédito' => 'cartao_credito',
            'cartao de credito' => 'cartao_credito',
            'crédito' => 'cartao_credito',
            'cartão de débito' => 'cartao_debito',
            'cartao de debito' => 'cartao_debito',
            'débito' => 'cartao_debito',
            'pix' => 'pix',
            'vale alimentação' => 'vale_alimentacao',
            'vale refeição' => 'vale_refeicao',
        ];

        $linhas = $xpath->query("//*[@id='totalNota']//*[@id='linhaTotal']");

        foreach ($linhas as $linha) {
            $label = $xpath->query(".//label[contains(@class,'tx')]", $linha)->item(0);
            $valor = $xpath->query(".//span[contains(@class,'totalNumb')]", $linha)->item(0);

            if ($label && $valor) {
                $formaTexto = mb_strtolower(trim($label->textContent));
                if ($formaTexto === 'troco' || str_contains($formaTexto, 'troco')) {
                    continue;
                }

                $forma = 'outros';
                foreach ($formasPagamentoMap as $key => $mapped) {
                    if (str_contains($formaTexto, $key)) {
                        $forma = $mapped;
                        break;
                    }
                }

                $pagamentos[] = [
                    'forma' => $forma,
                    'valor' => $this->parseBrDecimal(trim($valor->textContent)),
                ];
            }
        }

        return $pagamentos;
    }

    private function extrairMetadadosDoHtml(\DOMXPath $xpath): array
    {
        $numero = '';
        $serie = '';
        $emissao = '';

        $infoNode = $xpath->query("//*[@id='infos']//li")->item(0);
        if ($infoNode) {
            $text = $infoNode->textContent;

            if (preg_match('/Número:\s*(\d+)/', $text, $m)) {
                $numero = $m[1];
            }
            if (preg_match('/Série:\s*(\d+)/', $text, $m)) {
                $serie = $m[1];
            }
            if (preg_match('/Emissão:\s*([\d\/]+\s+[\d:]+)/', $text, $m)) {
                $emissao = $m[1];
            }
        }

        return [
            'numero' => $numero,
            'serie' => $serie,
            'emitido_em' => $emissao,
        ];
    }

    public function normalizarDadosPortal(array $dadosHtml, string $chaveAcesso): array
    {
        $emitente = $dadosHtml['emitente'] ?? [];
        $totais = $dadosHtml['totais'] ?? [];
        $metadados = $dadosHtml['metadados'] ?? [];

        $itens = array_map(fn (array $item) => [
            'numero_item' => $item['numero_item'],
            'codigo' => $item['codigo'] ?? '',
            'descricao' => $item['descricao'] ?? '',
            'ncm' => '',
            'cfop' => '',
            'unidade' => $item['unidade'] ?? '',
            'quantidade' => $item['quantidade'] ?? 0.0,
            'valor_unitario' => $item['valor_unitario'] ?? 0.0,
            'valor_total' => $item['valor_total'] ?? 0.0,
        ], $dadosHtml['itens'] ?? []);

        $emitidoEm = $metadados['emitido_em'] ?? '';
        if ($emitidoEm && preg_match('#(\d{2})/(\d{2})/(\d{4})\s+([\d:]+)#', $emitidoEm, $m)) {
            $emitidoEm = "{$m[3]}-{$m[2]}-{$m[1]}T{$m[4]}";
        }

        return [
            'chave' => $chaveAcesso,
            'numero' => $metadados['numero'] ?? '',
            'serie' => $metadados['serie'] ?? '',
            'emitido_em' => $emitidoEm,
            'ambiente' => 'producao',
            'emitente' => [
                'cnpj' => $emitente['cnpj'] ?? '',
                'nome' => $emitente['nome'] ?? '',
                'logradouro' => $emitente['logradouro'] ?? '',
                'numero' => $emitente['numero'] ?? '',
                'bairro' => $emitente['bairro'] ?? '',
                'municipio' => $emitente['municipio'] ?? '',
                'uf' => $emitente['uf'] ?? '',
                'cep' => $emitente['cep'] ?? '',
            ],
            'destinatario' => [
                'cpf' => '',
                'cnpj' => '',
                'nome' => '',
            ],
            'itens' => $itens,
            'total' => [
                'base_calculo_icms' => 0.0,
                'valor_icms' => 0.0,
                'valor_produtos' => $totais['valor_produtos'] ?? 0.0,
                'valor_nota' => $totais['valor_nota'] ?? 0.0,
                'valor_tributos' => $totais['valor_tributos'] ?? 0.0,
            ],
            'pagamento' => $dadosHtml['pagamento'] ?? [],
        ];
    }

    private function parseBrDecimal(string $value): float
    {
        if (empty($value)) {
            return 0.0;
        }

        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }

    /**
     * Baixa o XML da NFC-e autorizada a partir da chave de acesso.
     * Retorna o XML do procNFe (NFC-e + protocolo de autorização).
     *
     * @throws \RuntimeException Se a nota não estiver autorizada ou o XML não estiver disponível.
     */
    public function downloadXml(string $key): string
    {
        $xmlResponse = $this->getTools()->sefazConsultaChave($key);

        $dom = new \DOMDocument;
        $dom->loadXML($xmlResponse);

        // Verifica se a nota está autorizada (cStat = 100)
        $cStat = $dom->getElementsByTagName('cStat')->item(0);
        if (! $cStat || $cStat->nodeValue !== '100') {
            $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0);
            throw new \RuntimeException(
                'NFC-e não autorizada ou XML indisponível. Status: '.($xMotivo?->nodeValue ?? 'desconhecido')
            );
        }

        // Extrai o nfeProc (NFC-e + protocolo de autorização)
        $nfeProc = $dom->getElementsByTagName('nfeProc')->item(0);
        if (! $nfeProc) {
            throw new \RuntimeException('XML da NFC-e não encontrado na resposta da SEFAZ.');
        }

        $procDom = new \DOMDocument('1.0', 'UTF-8');
        $procDom->appendChild($procDom->importNode($nfeProc, true));

        return $procDom->saveXML();
    }

    public function extrairUF(string $key): string
    {
        return UFList::getUFByCode((int) substr($key, 0, 2));
    }

    public function extrairCNPJ(string $key): string
    {
        return substr($key, 6, 14);
    }

    public function extrairNumeroNota(string $key): string
    {
        return substr($key, 25, 9);
    }
}
