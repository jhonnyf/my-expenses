<?php

namespace App\Import\Strategies;

use App\Contracts\ImportStrategyInterface;
use App\DTOs\ImportPayload;
use App\Imports\NfceXmlImporter;
use Illuminate\Http\Request;

class XmlFileImportStrategy implements ImportStrategyInterface
{
    public function __construct(private readonly NfceXmlImporter $importer) {}

    public function getErrorField(): string
    {
        return 'xml';
    }

    public function resolve(Request $request): ImportPayload
    {
        $file = $request->file('xml');

        return new ImportPayload(
            parsed: $this->importer->fromFile($file->getRealPath()),
            rawContent: $file->get(),
        );
    }
}
