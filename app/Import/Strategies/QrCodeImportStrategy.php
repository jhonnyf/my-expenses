<?php

namespace App\Import\Strategies;

use App\Contracts\ImportStrategyInterface;
use App\DTOs\ImportPayload;
use App\Enums\InvoiceStatus;
use App\Imports\NfceXmlImporter;
use App\Services\NFCeService;
use Illuminate\Http\Request;

class QrCodeImportStrategy implements ImportStrategyInterface
{
    public function __construct(
        private readonly NFCeService $nfceService,
        private readonly NfceXmlImporter $importer,
    ) {}

    public function getErrorField(): string
    {
        return 'qrcode_url';
    }

    public function resolve(Request $request): ImportPayload
    {
        $url = $request->input('qrcode_url');
        $chave = $this->nfceService->extrairChaveDeUrl($url);

        if (! $chave) {
            throw new \InvalidArgumentException('Não foi possível extrair a chave de acesso da URL.');
        }

        if ($this->nfceService->isCertificadoConfigurado()) {
            try {
                $xml = $this->nfceService->downloadXml($chave);

                return new ImportPayload(
                    parsed: $this->importer->fromString($xml),
                    rawContent: $xml,
                );
            } catch (\Throwable) {
                // fallback para scraping HTML
            }
        }

        $resultado = $this->nfceService->consultarPorQRCode($url, $chave);

        if (empty($resultado['dados']['itens'])) {
            return $this->pendingPayload($url, $chave);
        }

        return new ImportPayload(
            parsed: $this->nfceService->normalizarDadosPortal($resultado['dados'], $chave),
            rawContent: $resultado['html'],
        );
    }

    /**
     * Portal sem itens: em contingência é uma nota ainda não autorizada (guardamos o que o QR informa e
     * o comando invoices:reconcile-pending completa depois); em emissão normal é falha de leitura.
     *
     * @throws \RuntimeException Se a nota não for de contingência.
     */
    private function pendingPayload(string $url, string $chave): ImportPayload
    {
        if (! $this->nfceService->isContingencia($chave)) {
            throw new \RuntimeException('O portal da SEFAZ não retornou os dados desta nota. Tente novamente em instantes.');
        }

        $provisorio = $this->nfceService->dadosProvisoriosDoQr($url);

        return new ImportPayload(
            parsed: [
                'chave' => $chave,
                'status' => InvoiceStatus::Pending,
                'qrcode_url' => $url,
                'numero' => ltrim($this->nfceService->extrairNumeroNota($chave), '0'),
                'serie' => (string) (int) $this->nfceService->extrairSerie($chave),
                'emitido_em' => $provisorio['emitido_em'] ?? null,
                'emitente' => ['cnpj' => $this->nfceService->extrairCNPJ($chave)],
                'total' => ['valor_nota' => $provisorio['valor_nota'] ?? 0],
            ],
            rawContent: '',
        );
    }
}
