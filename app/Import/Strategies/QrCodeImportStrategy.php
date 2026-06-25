<?php

namespace App\Import\Strategies;

use App\Contracts\ImportStrategyInterface;
use App\DTOs\ImportPayload;
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

        $resultado = $this->nfceService->consultarPorQRCode($url);

        return new ImportPayload(
            parsed: $this->nfceService->normalizarDadosPortal($resultado['dados'], $chave),
            rawContent: $resultado['html'],
        );
    }
}
