<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\SendReportByEmailJob;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function generate(Request $request): JsonResponse
    {
        $data = $this->service->buildReportData(
            $request->user()->id,
            $request->only(['start_date', 'end_date', 'issuer_id', 'category_id'])
        );

        return $this->success($data);
    }

    public function emailReport(Request $request): JsonResponse
    {
        $request->validate([
            'format' => ['required', 'in:pdf,csv'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'issuer_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        SendReportByEmailJob::dispatch(
            $request->user()->id,
            $request->input('format'),
            $request->only(['start_date', 'end_date', 'issuer_id', 'category_id'])
        );

        return $this->success(['scheduled' => true]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->service->buildReportData(
            $request->user()->id,
            $request->only(['start_date', 'end_date', 'issuer_id', 'category_id'])
        );

        return new StreamedResponse(function () use ($data) {
            echo $this->service->renderCsv($data);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio_'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
