<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ImportInvoiceAction;
use App\Contracts\ImportStrategyInterface;
use App\Events\InvoiceImported;
use App\Http\Requests\ImportByAccessKeyRequest;
use App\Http\Requests\ImportByQrCodeRequest;
use App\Http\Requests\UploadXmlRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Import\Strategies\AccessKeyImportStrategy;
use App\Import\Strategies\QrCodeImportStrategy;
use App\Import\Strategies\XmlFileImportStrategy;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ImportInvoiceAction $importAction,
        private readonly XmlFileImportStrategy $xmlStrategy,
        private readonly QrCodeImportStrategy $qrCodeStrategy,
        private readonly AccessKeyImportStrategy $accessKeyStrategy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->paginate();

        return InvoiceResource::collection($invoices)->response();
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== $request->user()->id, 403);

        $invoice->load('issuer', 'items.category', 'payments');

        return $this->success(new InvoiceResource($invoice));
    }

    public function importXml(UploadXmlRequest $request): JsonResponse
    {
        return $this->executeImport($request, $this->xmlStrategy);
    }

    public function importByQrCode(ImportByQrCodeRequest $request): JsonResponse
    {
        return $this->executeImport($request, $this->qrCodeStrategy);
    }

    public function importByKey(ImportByAccessKeyRequest $request): JsonResponse
    {
        return $this->executeImport($request, $this->accessKeyStrategy);
    }

    private function executeImport(FormRequest $request, ImportStrategyInterface $strategy): JsonResponse
    {
        try {
            $payload = $strategy->resolve($request);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->error($e->getMessage(), 422, [$strategy->getErrorField() => [$e->getMessage()]]);
        }

        $userId = $request->user()->id;

        if (Invoice::where('user_id', $userId)->where('access_key', $payload->parsed['chave'])->exists()) {
            return $this->error('Esta nota fiscal já foi importada anteriormente.', 409);
        }

        try {
            $invoice = $this->importAction->execute($payload->parsed, $payload->rawContent, $userId);
            InvoiceImported::dispatch($invoice);

            return $this->success(new InvoiceResource($invoice), 201);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
