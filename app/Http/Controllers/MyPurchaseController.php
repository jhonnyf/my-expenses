<?php

namespace App\Http\Controllers;

use App\Actions\ImportInvoiceAction;
use App\Contracts\ImportStrategyInterface;
use App\Events\InvoiceImported;
use App\Http\Requests\ImportByAccessKeyRequest;
use App\Http\Requests\ImportByQrCodeRequest;
use App\Http\Requests\UploadXmlRequest;
use App\Import\Strategies\AccessKeyImportStrategy;
use App\Import\Strategies\QrCodeImportStrategy;
use App\Import\Strategies\XmlFileImportStrategy;
use App\Models\Category;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyPurchaseController extends Controller
{
    public function __construct(
        private readonly ImportInvoiceAction $importAction,
        private readonly XmlFileImportStrategy $xmlStrategy,
        private readonly QrCodeImportStrategy $qrCodeStrategy,
        private readonly AccessKeyImportStrategy $accessKeyStrategy,
    ) {}

    public function index(): View
    {
        return view('my-purchase.index', [
            'records' => Invoice::where('user_id', Auth::id())->orderByDesc('issued_at')->paginate(),
        ]);
    }

    public function uploadForm(): View
    {
        return view('my-purchase.upload');
    }

    public function detail(Invoice $invoice): View
    {
        $user = Auth::user();
        abort_if($invoice->user_id !== $user->id, 403);

        $invoice->load('issuer', 'items.category', 'payments');

        $isIssuerFavorite = $invoice->issuer
            ? $user->favoriteIssuers()->where('issuers.id', $invoice->issuer_id)->exists()
            : false;

        $categories = Category::forUser($user->id)->orderBy('name')->get();

        return view('my-purchase.detail', [
            'invoice'          => $invoice,
            'isIssuerFavorite' => $isIssuerFavorite,
            'categories'       => $categories,
        ]);
    }

    public function upload(UploadXmlRequest $request): RedirectResponse
    {
        return $this->executeImport($request, $this->xmlStrategy);
    }

    public function importByQrCode(ImportByQrCodeRequest $request): RedirectResponse
    {
        return $this->executeImport($request, $this->qrCodeStrategy);
    }

    public function importByAccessKey(ImportByAccessKeyRequest $request): RedirectResponse
    {
        return $this->executeImport($request, $this->accessKeyStrategy);
    }

    private function executeImport(FormRequest $request, ImportStrategyInterface $strategy): RedirectResponse
    {
        $errorField = $strategy->getErrorField();

        try {
            $payload = $strategy->resolve($request);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors([$errorField => $e->getMessage()])->withInput();
        }

        $userId = Auth::id();

        if (Invoice::where('user_id', $userId)->where('access_key', $payload->parsed['chave'])->exists()) {
            return back()
                ->withErrors([$errorField => 'Esta nota fiscal já foi importada anteriormente.'])
                ->withInput();
        }

        try {
            $invoice = $this->importAction->execute($payload->parsed, $payload->rawContent, $userId);
            InvoiceImported::dispatch($invoice);

            return redirect()->route('my-purchases.detail', $invoice->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors([$errorField => $e->getMessage()])->withInput();
        }
    }
}
