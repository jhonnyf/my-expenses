<?php

namespace App\Http\Controllers;

use App\Actions\DeleteInvoiceAction;
use App\Actions\ImportInvoiceAction;
use App\Actions\LogQrCodeReadAction;
use App\Contracts\ImportStrategyInterface;
use App\Enums\InvoiceStatus;
use App\Events\InvoiceImported;
use App\Http\Requests\ImportByAccessKeyRequest;
use App\Http\Requests\ImportByQrCodeRequest;
use App\Http\Requests\ListInvoicesRequest;
use App\Http\Requests\UploadXmlRequest;
use App\Import\Strategies\AccessKeyImportStrategy;
use App\Import\Strategies\QrCodeImportStrategy;
use App\Import\Strategies\XmlFileImportStrategy;
use App\Models\Category;
use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\IssuerService;
use App\Services\ProductAliasService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyPurchaseController extends Controller
{
    public function __construct(
        private readonly ImportInvoiceAction $importAction,
        private readonly LogQrCodeReadAction $logQrCodeReadAction,
        private readonly XmlFileImportStrategy $xmlStrategy,
        private readonly QrCodeImportStrategy $qrCodeStrategy,
        private readonly AccessKeyImportStrategy $accessKeyStrategy,
        private readonly ProductAliasService $productAliasService,
        private readonly InvoiceService $invoices,
        private readonly IssuerService $issuers,
        private readonly DeleteInvoiceAction $deleteInvoiceAction,
    ) {}

    public function index(ListInvoicesRequest $request): View
    {
        $user = Auth::user();
        $filters = $request->filters();

        // A tela abre no mês corrente; a API, sem período, devolve tudo.
        $filters['start_date'] ??= Carbon::now()->startOfMonth()->format('Y-m-d');
        $filters['end_date'] ??= Carbon::now()->format('Y-m-d');

        $summary = $this->invoices->summaryForUser($user, $filters);

        return view('my-purchase.index', [
            'records' => $this->invoices->paginateForUser($user, $filters),
            'search' => $filters['search'],
            'filters' => ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']],
            'listFilters' => Arr::only($filters, ['search', 'issuer_id', 'status', 'sort']),
            'hasListFilters' => $filters['search'] !== '' || $filters['issuer_id'] !== null || $filters['status'] !== null,
            'issuerOptions' => $this->issuers->optionsForUser($user),
            'totalAmount' => $summary['total_amount'],
            'totalCount' => $summary['total_count'],
            'unconfirmedCount' => $summary['unconfirmed_count'],
            'dailyAverage' => $summary['daily_average'],
            'averageTicket' => $summary['average_ticket'],
            'deltaPct' => $summary['delta_pct'],
        ]);
    }

    public function uploadForm(): View
    {
        return view('my-purchase.upload');
    }

    public function detail(Invoice $invoice): View
    {
        $user = Auth::user();
        $this->authorize('view', $invoice);

        $invoice->load('issuer.nicknameForUser', 'items.category', 'payments');
        $this->productAliasService->attachCanonicalNames($invoice->items, $user->id);

        $isIssuerFavorite = $invoice->issuer
            ? $user->favoriteIssuers()->where('issuers.id', $invoice->issuer_id)->exists()
            : false;

        $favoriteProductNames = FavoriteProduct::where('user_id', $user->id)->pluck('canonical_name');

        $categories = Category::forUser($user->id)->orderBy('name')->get();

        return view('my-purchase.detail', [
            'invoice' => $invoice,
            'isIssuerFavorite' => $isIssuerFavorite,
            'favoriteProductNames' => $favoriteProductNames,
            'categories' => $categories,
        ]);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $this->deleteInvoiceAction->execute($invoice);

        return redirect()->route('my-purchases.index')->with('success', 'Nota fiscal excluída.');
    }

    public function upload(UploadXmlRequest $request): RedirectResponse|JsonResponse
    {
        return $this->executeImport($request, $this->xmlStrategy);
    }

    public function importByQrCode(ImportByQrCodeRequest $request): RedirectResponse|JsonResponse
    {
        return $this->executeImport($request, $this->qrCodeStrategy);
    }

    public function importByAccessKey(ImportByAccessKeyRequest $request): RedirectResponse|JsonResponse
    {
        return $this->executeImport($request, $this->accessKeyStrategy);
    }

    private function executeImport(FormRequest $request, ImportStrategyInterface $strategy): RedirectResponse|JsonResponse
    {
        $errorField = $strategy->getErrorField();
        $qrcodeUrl = $strategy instanceof QrCodeImportStrategy ? (string) $request->input('qrcode_url') : null;
        $userId = Auth::id();

        try {
            $payload = $strategy->resolve($request);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($qrcodeUrl !== null) {
                $this->logQrCodeReadAction->execute($userId, $qrcodeUrl, success: false, errorMessage: $e->getMessage());
            }

            return $this->importError($request, $errorField, $e->getMessage());
        }

        if (Invoice::where('user_id', $userId)->where('access_key', $payload->parsed['chave'])->exists()) {
            if ($qrcodeUrl !== null) {
                $this->logQrCodeReadAction->execute($userId, $qrcodeUrl, success: false, errorMessage: 'Esta nota fiscal já foi importada anteriormente.');
            }

            return $this->importError($request, $errorField, 'Esta nota fiscal já foi importada anteriormente.');
        }

        try {
            $invoice = $this->importAction->execute($payload->parsed, $payload->rawContent, $userId);

            if ($invoice->status === InvoiceStatus::Authorized) {
                InvoiceImported::dispatch($invoice);
            }

            if ($qrcodeUrl !== null) {
                $this->logQrCodeReadAction->execute($userId, $qrcodeUrl, success: true, invoiceId: $invoice->id);
            }

            if ($this->shouldRespondWithJson($request)) {
                return response()->json(['redirect' => route('my-purchases.detail', $invoice->id)]);
            }

            return redirect()->route('my-purchases.detail', $invoice->id);
        } catch (\InvalidArgumentException $e) {
            if ($qrcodeUrl !== null) {
                $this->logQrCodeReadAction->execute($userId, $qrcodeUrl, success: false, errorMessage: $e->getMessage());
            }

            return $this->importError($request, $errorField, $e->getMessage());
        }
    }

    private function importError(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        if ($this->shouldRespondWithJson($request)) {
            return response()->json(['errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }

    private function shouldRespondWithJson(Request $request): bool
    {
        return $request->wantsJson() || $request->is('api/*');
    }
}
