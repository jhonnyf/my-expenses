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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): View
    {
        $userId = Auth::id();
        $search = trim((string) $request->input('search', ''));

        $records = Invoice::where('user_id', $userId)
            ->with('issuer.nicknameForUser')
            ->when($search !== '', fn ($query) => $query->whereHas(
                'issuer',
                fn ($query) => $query->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('issued_at')
            ->paginate()
            ->withQueryString();

        $stats = Invoice::where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $monthAmount = Invoice::where('user_id', $userId)
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->sum('total_amount');

        return view('my-purchase.index', [
            'records' => $records,
            'search' => $search,
            'totalAmount' => (float) $stats->total_amount,
            'totalCount' => (int) $stats->total_count,
            'monthAmount' => (float) $monthAmount,
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

        $isIssuerFavorite = $invoice->issuer
            ? $user->favoriteIssuers()->where('issuers.id', $invoice->issuer_id)->exists()
            : false;

        $categories = Category::forUser($user->id)->orderBy('name')->get();

        return view('my-purchase.detail', [
            'invoice' => $invoice,
            'isIssuerFavorite' => $isIssuerFavorite,
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

        try {
            $payload = $strategy->resolve($request);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->importError($request, $errorField, $e->getMessage());
        }

        $userId = Auth::id();

        if (Invoice::where('user_id', $userId)->where('access_key', $payload->parsed['chave'])->exists()) {
            return $this->importError($request, $errorField, 'Esta nota fiscal já foi importada anteriormente.');
        }

        try {
            $invoice = $this->importAction->execute($payload->parsed, $payload->rawContent, $userId);
            InvoiceImported::dispatch($invoice);

            if ($request->wantsJson()) {
                return response()->json(['redirect' => route('my-purchases.detail', $invoice->id)]);
            }

            return redirect()->route('my-purchases.detail', $invoice->id);
        } catch (\InvalidArgumentException $e) {
            return $this->importError($request, $errorField, $e->getMessage());
        }
    }

    private function importError(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }
}
