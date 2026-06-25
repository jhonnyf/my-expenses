<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function index(): View
    {
        return view('report.index', $this->service->buildReportData(Auth::id(), []));
    }

    public function generate(Request $request): View
    {
        return view('report.index', $this->service->buildReportData(Auth::id(), $request->only([
            'start_date', 'end_date', 'issuer_id', 'category_id',
        ])));
    }

    public function exportPdf(Request $request): Response
    {
        $pdf = Pdf::loadView('report.pdf', $this->service->buildReportData(Auth::id(), $request->only([
            'start_date', 'end_date', 'issuer_id', 'category_id',
        ])));

        return $pdf->download('relatorio.pdf');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->service->buildReportData(Auth::id(), $request->only([
            'start_date', 'end_date', 'issuer_id', 'category_id',
        ]));

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
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio.csv"',
        ]);
    }
}
