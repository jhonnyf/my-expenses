<?php

namespace App\Http\Controllers;

use App\Imports\NfceXmlImporter;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class NfceImportController extends Controller
{
    public function __construct(
        private readonly NfceXmlImporter $importer,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function importar(Request $request)
    {
        $path = public_path('/import/nfc-e.xml');

        try {
            $dados = $this->importer->fromFile($path);
            $invoice = $this->invoiceService->createFromParsed($dados);

            return response()->json($invoice, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
