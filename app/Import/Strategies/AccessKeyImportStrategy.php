<?php

namespace App\Import\Strategies;

use App\Contracts\ImportStrategyInterface;
use App\DTOs\ImportPayload;
use App\Imports\NfceXmlImporter;
use App\Services\NFCeService;
use Illuminate\Http\Request;

class AccessKeyImportStrategy implements ImportStrategyInterface
{
    public function __construct(
        private readonly NFCeService $nfceService,
        private readonly NfceXmlImporter $importer,
    ) {}

    public function getErrorField(): string
    {
        return 'access_key';
    }

    public function resolve(Request $request): ImportPayload
    {
        if (! $this->nfceService->isCertificadoConfigurado()) {
            throw new \InvalidArgumentException(
                'Certificado digital não configurado. Configure as variáveis NFE_CERTIFICADO_PATH e NFE_CERTIFICADO_SENHA no arquivo .env.'
            );
        }

        $xml = $this->nfceService->downloadXml($request->input('access_key'));

        return new ImportPayload(
            parsed: $this->importer->fromString($xml),
            rawContent: $xml,
        );
    }
}
