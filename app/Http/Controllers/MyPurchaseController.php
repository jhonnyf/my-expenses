<?php

namespace App\Http\Controllers;

use App\Actions\ImportInvoiceAction;
use App\Actions\LogQrCodeReadAction;
use App\Contracts\ImportStrategyInterface;
use App\Events\InvoiceImported;
use App\Http\Requests\ImportByAccessKeyRequest;
use App\Http\Requests\ImportByQrCodeRequest;
use App\Http\Requests\UploadXmlRequest;
use App\Import\Strategies\AccessKeyImportStrategy;
use App\Import\Strategies\QrCodeImportStrategy;
use App\Import\Strategies\XmlFileImportStrategy;
use App\Models\Category;
use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Services\ProductAliasService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ) {}

    public function index(Request $request): View
    {
        $userId = Auth::id();
        $search = trim((string) $request->input('search', ''));
        $start = $request->query('start_date') ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $end = $request->query('end_date') ?: Carbon::now()->format('Y-m-d');

        $records = Invoice::where('user_id', $userId)
            ->with('issuer.nicknameForUser')
            ->whereDateBetween('issued_at', $start, $end)
            ->when($search !== '', fn ($query) => $query->whereHas(
                'issuer',
                fn ($query) => $query->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('issued_at')
            ->paginate()
            ->withQueryString();

        $stats = Invoice::where('user_id', $userId)
            ->whereDateBetween('issued_at', $start, $end)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;

        return view('my-purchase.index', [
            'records' => $records,
            'search' => $search,
            'filters' => ['start_date' => $start, 'end_date' => $end],
            'totalAmount' => (float) $stats->total_amount,
            'totalCount' => (int) $stats->total_count,
            'dailyAverage' => $days > 0 ? $stats->total_amount / $days : 0.0,
            'averageTicket' => $stats->total_count > 0 ? $stats->total_amount / $stats->total_count : 0,
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
            InvoiceImported::dispatch($invoice);

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
