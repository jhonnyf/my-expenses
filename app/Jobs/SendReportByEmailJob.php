<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ReportByEmail;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReportByEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly int $userId,
        private readonly string $format,
        private readonly array $filters,
    ) {}

    public function handle(ReportService $service): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $data = $service->buildReportData($user->id, $this->filters);

        $content = $this->format === 'pdf'
            ? Pdf::loadView('report.pdf', $data)->output()
            : $service->renderCsv($data);

        // Notificação não é ShouldQueue: o conteúdo binário do anexo não serializa bem na fila.
        $user->notify(new ReportByEmail(
            $content,
            'relatorio_'.now()->format('Y-m-d').'.'.$this->format,
            $this->format === 'pdf' ? 'application/pdf' : 'text/csv',
        ));
    }
}
