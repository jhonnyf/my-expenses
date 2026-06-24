<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $issuers = Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('name')
            ->get();

        $categories = Category::forUser($userId)->orderBy('name')->get();

        return view('report.index', [
            'issuers' => $issuers,
            'categories' => $categories,
        ]);
    }

    public function generate(Request $request)
    {
        $data = $this->buildReportData($request);

        return view('report.index', $data);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildReportData($request);

        $pdf = Pdf::loadView('report.pdf', $data);

        return $pdf->download('relatorio.pdf');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->buildReportData($request);

        return new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['Data', 'Emissor', 'Produto', 'Categoria', 'Qtd', 'Unidade', 'Preço Unit.', 'Total'], ';');

            foreach ($data['items'] as $item) {
                fputcsv($handle, [
                    Carbon::parse($item->issued_at)->format('d/m/Y'),
                    $item->issuer_name,
                    $item->description,
                    $item->category_name ?? 'Sem categoria',
                    number_format($item->quantity, 4, ',', '.'),
                    $item->unit,
                    number_format($item->unit_price, 2, ',', '.'),
                    number_format($item->total_price, 2, ',', '.'),
                ], ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio.csv"',
        ]);
    }

    private function buildReportData(Request $request): array
    {
        $userId = Auth::id();
        $startDate = $request->input('start_date') ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->input('end_date') ?: Carbon::now()->format('Y-m-d');
        $issuerId = $request->input('issuer_id');
        $categoryId = $request->input('category_id');

        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('categories', 'categories.id', '=', 'invoices_items.category_id')
            ->where('invoices.user_id', $userId)
            ->when($startDate, fn ($q) => $q->whereDate('invoices.issued_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('invoices.issued_at', '<=', $endDate))
            ->when($issuerId, fn ($q) => $q->where('invoices.issuer_id', $issuerId))
            ->when($categoryId, fn ($q) => $q->where('invoices_items.category_id', $categoryId));

        $items = (clone $query)->select(
            'invoices_items.description',
            'invoices_items.quantity',
            'invoices_items.unit',
            'invoices_items.unit_price',
            'invoices_items.total_price',
            'invoices.issued_at',
            'issuers.name as issuer_name',
            DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
            DB::raw("COALESCE(categories.color, '#94A3B8') as category_color")
        )
            ->orderByDesc('invoices.issued_at')
            ->get();

        $summary = (clone $query)->select(
            DB::raw('SUM(invoices_items.total_price) as total_amount'),
            DB::raw('COUNT(invoices_items.id) as total_items'),
            DB::raw('COUNT(DISTINCT invoices.id) as total_invoices')
        )->first();

        $categoryBreakdown = (clone $query)->select(
            DB::raw("COALESCE(categories.name, 'Sem categoria') as category_name"),
            DB::raw("COALESCE(categories.color, '#94A3B8') as category_color"),
            DB::raw('SUM(invoices_items.total_price) as total')
        )
            ->groupBy('category_name', 'category_color')
            ->orderByDesc('total')
            ->get();

        $issuers = Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('name')
            ->get();

        $categories = Category::forUser($userId)->orderBy('name')->get();

        return [
            'items' => $items,
            'summary' => $summary,
            'categoryBreakdown' => $categoryBreakdown,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'issuer_id' => $issuerId,
                'category_id' => $categoryId,
            ],
            'issuers' => $issuers,
            'categories' => $categories,
        ];
    }
}
