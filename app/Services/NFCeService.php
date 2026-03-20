<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\Common\UFList;

class NFCeService
{
    private ?Tools $tools = null;

    private function getTools(): Tools
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $pfxPath     = config('nfe.certificado_path');
        $pfxPassword = config('nfe.certificado_senha');
        $cnpj        = config('nfe.cnpj');
        $razao       = config('nfe.razao_social');
        $uf          = config('nfe.uf');
        $tpAmb       = (int) config('nfe.ambiente', 2);

        if (!file_exists($pfxPath)) {
            throw new \RuntimeException("Certificado não encontrado em: {$pfxPath}");
        }

        $certificate = Certificate::readPfx(file_get_contents($pfxPath), $pfxPassword);

        $configJson = json_encode([
            'atualizacao' => now()->format('Y-m-d H:i:s'),
            'tpAmb'       => $tpAmb,
            'razaosocial' => $razao,
            'cnpj'        => $cnpj,
            'siglaUF'     => $uf,
            'schemes'     => realpath(base_path('vendor/nfephp-org/sped-nfe/schemes')),
            'versao'      => '4.00',
            'tokenIBPT'   => '',
            'CSC'         => config('nfe.csc', ''),
            'CSCid'       => config('nfe.csc_id', ''),
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
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $result = [];

        // Status da consulta
        $cStat  = $dom->getElementsByTagName('cStat')->item(0);
        $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0);

        $result['status'] = [
            'codigo'   => $cStat?->nodeValue,
            'descricao' => $xMotivo?->nodeValue,
        ];

        // Protocolo de autorização
        $nProt     = $dom->getElementsByTagName('nProt')->item(0);
        $dhRecbto  = $dom->getElementsByTagName('dhRecbto')->item(0);
        if ($nProt) {
            $result['protocolo'] = [
                'numero'        => $nProt->nodeValue,
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
                    'cnpj'          => $this->getTagValue($emit, 'CNPJ'),
                    'razao_social'  => $this->getTagValue($emit, 'xNome'),
                    'fantasia'      => $this->getTagValue($emit, 'xFant'),
                    'ie'            => $this->getTagValue($emit, 'IE'),
                    'uf'            => $this->getTagValue($emit, 'UF'),
                    'municipio'     => $this->getTagValue($emit, 'xMun'),
                ];
            }

            // Destinatário
            $dest = $dom->getElementsByTagName('dest')->item(0);
            if ($dest) {
                $result['destinatario'] = [
                    'cnpj_cpf'     => $this->getTagValue($dest, 'CNPJ') ?: $this->getTagValue($dest, 'CPF'),
                    'razao_social' => $this->getTagValue($dest, 'xNome'),
                    'uf'           => $this->getTagValue($dest, 'UF'),
                ];
            }

            // Totais
            $total = $dom->getElementsByTagName('ICMSTot')->item(0);
            if ($total) {
                $result['totais'] = [
                    'valor_produtos' => $this->getTagValue($total, 'vProd'),
                    'valor_desconto' => $this->getTagValue($total, 'vDesc'),
                    'valor_frete'    => $this->getTagValue($total, 'vFrete'),
                    'valor_total'    => $this->getTagValue($total, 'vNF'),
                    'valor_icms'     => $this->getTagValue($total, 'vICMS'),
                ];
            }

            // Itens
            $items = [];
            foreach ($dom->getElementsByTagName('det') as $det) {
                $prod = $det->getElementsByTagName('prod')->item(0);
                if ($prod) {
                    $items[] = [
                        'item'       => $det->getAttribute('nItem'),
                        'codigo'     => $this->getTagValue($prod, 'cProd'),
                        'descricao'  => $this->getTagValue($prod, 'xProd'),
                        'ncm'        => $this->getTagValue($prod, 'NCM'),
                        'quantidade' => $this->getTagValue($prod, 'qCom'),
                        'unidade'    => $this->getTagValue($prod, 'uCom'),
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
                    'tipo'  => $this->getTagValue($pag, 'tPag'),
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

    /**
     * Consulta a NFC-e a partir da URL do QR Code impresso no cupom.
     * Não requer certificado digital nem CSC.
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
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Erro ao consultar portal SEFAZ: HTTP {$response->status()}");
        }

        return $this->parseHtmlPortal($response->body());
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

        if (!$host) {
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

    /**
     * Extrai dados do HTML do portal do consumidor da SEFAZ.
     */
    private function parseHtmlPortal(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        $extrair = function (string $seletor) use ($xpath): ?string {
            $nodes = $xpath->query($seletor);
            return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
        };

        // Tenta extrair campos comuns presentes nos portais da maioria dos estados
        $data = [
            'emitente'     => [
                'razao_social' => $extrair("//*[@id='u20']") ?? $extrair("//*[contains(@class,'txtTopo')]"),
                'cnpj'         => $extrair("//*[@id='u07']"),
                'endereco'     => $extrair("//*[@id='u08']"),
            ],
            'totais'       => [
                'valor_total'    => $extrair("//*[@id='linhaTotal']//span[contains(@class,'totalNumb')]") ?? $extrair("//*[contains(@class,'totalNumb')]"),
                'valor_desconto' => $extrair("//*[@id='vDescItens']") ?? $extrair("//*[@id='vDesc']"),
            ],
            'itens'        => [],
            'raw_disponivel' => !empty($html),
        ];

        // Tenta extrair itens da tabela de produtos
        $linhas = $xpath->query("//*[@id='tuberculoData']//tr | //table[contains(@class,'box-body')]//tr");
        foreach ($linhas as $linha) {
            $colunas = $xpath->query('.//td', $linha);
            if ($colunas->length >= 3) {
                $data['itens'][] = [
                    'descricao'  => trim($colunas->item(0)->textContent ?? ''),
                    'quantidade' => trim($colunas->item(1)->textContent ?? ''),
                    'valor'      => trim($colunas->item(2)->textContent ?? ''),
                ];
            }
        }

        // Remove itens vazios
        $data['itens'] = array_values(array_filter($data['itens'], fn($i) => !empty($i['descricao'])));

        return $data;
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

        $dom = new \DOMDocument();
        $dom->loadXML($xmlResponse);

        // Verifica se a nota está autorizada (cStat = 100)
        $cStat = $dom->getElementsByTagName('cStat')->item(0);
        if (!$cStat || $cStat->nodeValue !== '100') {
            $xMotivo = $dom->getElementsByTagName('xMotivo')->item(0);
            throw new \RuntimeException(
                'NFC-e não autorizada ou XML indisponível. Status: ' . ($xMotivo?->nodeValue ?? 'desconhecido')
            );
        }

        // Extrai o nfeProc (NFC-e + protocolo de autorização)
        $nfeProc = $dom->getElementsByTagName('nfeProc')->item(0);
        if (!$nfeProc) {
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
