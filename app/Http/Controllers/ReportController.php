<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportEmailRequest;
use App\Http\Requests\ReportFiltersRequest;
use App\Http\Requests\SaveReportScheduleRequest;
use App\Jobs\SendReportByEmailJob;
use App\Models\ReportSchedule;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service) {}

    public function index(ReportFiltersRequest $request): View
    {
        return $this->render($request);
    }

    /** O formulário antigo enviava por POST; a tela renderiza igual (paginação e ordenação usam GET em `index`). */
    public function generate(ReportFiltersRequest $request): View
    {
        return $this->render($request);
    }

    public function exportPdf(ReportFiltersRequest $request): Response
    {
        $pdf = Pdf::loadView('report.pdf', $this->service->buildReportData(
            Auth::id(),
            $request->filters(),
            ReportService::PDF_MAX_ITEMS,
        ));

        return $pdf->download('relatorio_'.now()->format('Y-m-d').'.pdf');
    }

    public function exportCsv(ReportFiltersRequest $request): StreamedResponse
    {
        $userId = Auth::id();
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

    public function email(ReportEmailRequest $request): JsonResponse
    {
        SendReportByEmailJob::dispatch(Auth::id(), $request->input('format'), $request->filters());

        return response()->json(['scheduled' => true]);
    }

    public function saveSchedule(SaveReportScheduleRequest $request): JsonResponse
    {
        $schedule = ReportSchedule::updateOrCreate(
            ['user_id' => Auth::id()],
            ['frequency' => $request->input('frequency'), 'format' => $request->input('format')],
        );

        return response()->json($this->schedulePayload($schedule));
    }

    public function deleteSchedule(): JsonResponse
    {
        ReportSchedule::where('user_id', Auth::id())->delete();

        return response()->json(['success' => true]);
    }

    private function render(ReportFiltersRequest $request): View
    {
        $data = $this->service->buildReportPage(Auth::id(), $request->filters(), $request->perPage());

        // Links de paginação sempre em GET `index`, mesmo quando a tela veio de um POST.
        $data['items']->withPath(route('reports.index'))->appends(array_filter($data['filters'], fn ($v) => $v !== null && $v !== ''));

        return view('report.index', [
            ...$data,
            'schedule' => ReportSchedule::where('user_id', Auth::id())->first(),
            'isPro' => Auth::user()->isPro(),
        ]);
    }

    /**
     * @return array{frequency: string, format: string, frequency_label: string, next_run: string, last_sent_on: ?string}
     */
    private function schedulePayload(ReportSchedule $schedule): array
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
