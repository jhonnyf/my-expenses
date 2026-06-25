<?php

namespace App\Http\Controllers;

use App\Imports\NfceXmlImporter;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Issuer;
use App\Services\CategoryService;
use App\Services\NFCeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MyPurchaseController extends Controller
{
    public function __construct(
        private readonly NfceXmlImporter $importer,
        private readonly NFCeService $nfceService,
        private readonly CategoryService $categoryService,
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
            'invoice' => $invoice,
            'isIssuerFavorite' => $isIssuerFavorite,
            'categories' => $categories,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'xml' => ['required', 'file', 'mimes:xml,text/xml', 'max:10240'],
        ]);

        $file = $request->file('xml');
        $dados = $this->importer->fromFile($file->getRealPath());

        return $this->processImport($dados, $file->get(), 'xml');
    }

    public function importByQrCode(Request $request): RedirectResponse
    {
        $request->validate([
            'qrcode_url' => ['required', 'string', 'regex:/^https?:\/\/.+/i'],
        ]);

        $url = $request->input('qrcode_url');
        $chave = $this->nfceService->extrairChaveDeUrl($url);

        if (!$chave) {
            return back()
                ->withErrors(['qrcode_url' => 'Não foi possível extrair a chave de acesso da URL.'])
                ->withInput();
        }

        if ($this->nfceService->isCertificadoConfigurado()) {
            try {
                return $this->importViaXmlSefaz($chave, 'qrcode_url');
            } catch (\Throwable) {
                // fallback para scraping HTML
            }
        }

        try {
            $resultado = $this->nfceService->consultarPorQRCode($url);
            $dados = $this->nfceService->normalizarDadosPortal($resultado['dados'], $chave);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()
                ->withErrors(['qrcode_url' => $e->getMessage()])
                ->withInput();
        }

        return $this->processImport($dados, $resultado['html'], 'qrcode_url');
    }

    public function importByAccessKey(Request $request): RedirectResponse
    {
        $request->merge([
            'access_key' => preg_replace('/\D/', '', $request->input('access_key', '')),
        ]);

        $request->validate([
            'access_key' => ['required', 'string', 'regex:/^\d{44}$/'],
        ]);

        if (!$this->nfceService->isCertificadoConfigurado()) {
            return back()
                ->withErrors(['access_key' => 'Certificado digital não configurado. Configure as variáveis NFE_CERTIFICADO_PATH e NFE_CERTIFICADO_SENHA no arquivo .env.'])
                ->withInput();
        }

        try {
            return $this->importViaXmlSefaz($request->input('access_key'), 'access_key');
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()
                ->withErrors(['access_key' => $e->getMessage()])
                ->withInput();
        }
    }

    private function importViaXmlSefaz(string $chave, string $errorField): RedirectResponse
    {
        $xml = $this->nfceService->downloadXml($chave);
        $dados = $this->importer->fromString($xml);

        return $this->processImport($dados, $xml, $errorField);
    }

    private function processImport(array $dados, string $xmlContent, string $errorField): RedirectResponse
    {
        $userId = Auth::id();

        if (Invoice::where('user_id', $userId)->where('access_key', $dados['chave'])->exists()) {
            return back()
                ->withErrors([$errorField => 'Esta nota fiscal já foi importada anteriormente.'])
                ->withInput();
        }

        try {
            $invoice = DB::transaction(fn () => $this->storeInvoice($dados, $xmlContent, $userId));
            $this->categoryService->autoCategorize($userId);

            return redirect()->route('my-purchases.detail', $invoice->id);
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors([$errorField => $e->getMessage()])
                ->withInput();
        }
    }

    private function storeInvoice(array $dados, string $xmlContent, int $userId): Invoice
    {
        $issuer = $this->findOrCreateIssuer(Arr::get($dados, 'emitente', []));

        $invoice = Invoice::updateOrCreate(
            ['access_key' => Arr::get($dados, 'chave')],
            $this->invoiceAttributes($dados, $issuer, $xmlContent, $userId)
        );

        $this->syncItems($invoice, Arr::get($dados, 'itens', []));
        $this->syncPayments($invoice, Arr::get($dados, 'pagamento', []));

        return $invoice;
    }

    private function findOrCreateIssuer(array $emitente): ?Issuer
    {
        $cnpj = Arr::get($emitente, 'cnpj');

        if (empty($cnpj)) {
            return null;
        }

        return Issuer::firstOrCreate(
            ['cnpj' => $cnpj],
            [
                'name' => Arr::get($emitente, 'nome', ''),
                'street' => Arr::get($emitente, 'logradouro', ''),
                'street_number' => Arr::get($emitente, 'numero', ''),
                'neighborhood' => Arr::get($emitente, 'bairro', ''),
                'city' => Arr::get($emitente, 'municipio', ''),
                'state' => Arr::get($emitente, 'uf', ''),
                'zip_code' => Arr::get($emitente, 'cep', ''),
            ]
        );
    }

    private function invoiceAttributes(array $dados, ?Issuer $issuer, string $xmlContent, int $userId): array
    {
        return [
            'user_id' => $userId,
            'number' => Arr::get($dados, 'numero', ''),
            'series' => Arr::get($dados, 'serie', ''),
            'issued_at' => Arr::get($dados, 'emitido_em', now()),
            'environment' => Arr::get($dados, 'ambiente', 'producao') === 'producao' ? 'production' : 'staging',
            'issuer_id' => $issuer?->id,
            'total_icms_base' => (float) Arr::get($dados, 'total.base_calculo_icms', 0),
            'total_icms' => (float) Arr::get($dados, 'total.valor_icms', 0),
            'total_products' => (float) Arr::get($dados, 'total.valor_produtos', 0),
            'total_amount' => (float) Arr::get($dados, 'total.valor_nota', 0),
            'total_taxes' => (float) Arr::get($dados, 'total.valor_tributos', 0),
            'raw_xml' => $xmlContent,
        ];
    }

    private function syncPayments(Invoice $invoice, array $payments): void
    {
        $invoice->payments()->delete();

        if (empty($payments)) {
            return;
        }

        $now = now();
        InvoicePayment::insert(
            array_map(fn (array $payment) => [
                'invoice_id' => $invoice->id,
                'method' => Arr::get($payment, 'forma', 'outros'),
                'amount' => (float) Arr::get($payment, 'valor', 0),
                'created_at' => $now,
                'updated_at' => $now,
            ], $payments)
        );
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            InvoiceItem::updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'item_number' => (int) Arr::get($item, 'numero_item', 0),
                ],
                [
                    'code' => Arr::get($item, 'codigo', ''),
                    'description' => Arr::get($item, 'descricao', ''),
                    'ncm' => Arr::get($item, 'ncm', ''),
                    'cfop' => Arr::get($item, 'cfop', ''),
                    'unit' => Arr::get($item, 'unidade', ''),
                    'quantity' => (float) Arr::get($item, 'quantidade', 0),
                    'unit_price' => (float) Arr::get($item, 'valor_unitario', 0),
                    'total_price' => (float) Arr::get($item, 'valor_total', 0),
                ]
            );
        }
    }
}
