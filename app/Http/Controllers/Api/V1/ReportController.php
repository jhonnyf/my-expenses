<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReportEmailRequest;
use App\Http\Requests\ReportFiltersRequest;
use App\Http\Requests\SaveReportScheduleRequest;
use App\Jobs\SendReportByEmailJob;
use App\Models\ReportSchedule;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    /**
     * `items` vem paginado (`page`, `per_page` até 200; padrão 50) com `items_meta`; os totais, gráficos e
     * quebras cobrem o período inteiro.
     */
    public function generate(ReportFiltersRequest $request): JsonResponse
    {
        $data = $this->service->buildReportPage($request->user()->id, $request->filters(), $request->perPage());

        $items = $data['items'];
        $data['items'] = $items->items();
        $data['items_meta'] = [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
        ];

        return $this->success($data);
    }

    public function emailReport(ReportEmailRequest $request): JsonResponse
    {
        SendReportByEmailJob::dispatch($request->user()->id, $request->input('format'), $request->filters());

        return $this->success(['scheduled' => true]);
    }

    public function exportCsv(ReportFiltersRequest $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $filters = $request->filters();

        return new StreamedResponse(function () use ($userId, $filters) {
            $handle = fopen('php://output', 'w');
            $this->service->streamCsv($handle, $userId, $filters);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="relatorio_'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $schedule = ReportSchedule::where('user_id', $request->user()->id)->first();

        return $this->success($schedule ? $this->payload($schedule) : null);
    }

    public function saveSchedule(SaveReportScheduleRequest $request): JsonResponse
    {
        $schedule = ReportSchedule::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['frequency' => $request->input('frequency'), 'format' => $request->input('format')],
        );

        return $this->success($this->payload($schedule));
    }

    public function deleteSchedule(Request $request): JsonResponse
    {
        ReportSchedule::where('user_id', $request->user()->id)->delete();

        return $this->success(['success' => true]);
    }

    /**
     * @return array{frequency: string, format: string, frequency_label: string, next_run: string, last_sent_on: ?string}
     */
    private function payload(ReportSchedule $schedule): array
    {
        return [
            'frequency' => $schedule->frequency->value,
            'format' => $schedule->format,
            'frequency_label' => $schedule->frequency->label(),
            'next_run' => $schedule->frequency->nextRunAfter(now())->toDateString(),
            'last_sent_on' => $schedule->last_sent_on?->toDateString(),
        ];
    }
}
