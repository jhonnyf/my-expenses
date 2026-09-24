<?php

namespace App\Console\Commands;

use App\Jobs\SendReportByEmailJob;
use App\Models\ReportSchedule;
use Illuminate\Console\Command;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Envia por e-mail os relatórios agendados que vencem hoje (plano Pro)';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        ReportSchedule::with('user.subscription')->chunkById(200, function ($schedules) use ($today, &$sent) {
            foreach ($schedules as $schedule) {
                if (! $schedule->user?->isPro() || ! $schedule->isDueOn($today)) {
                    continue;
                }

                SendReportByEmailJob::dispatch($schedule->user_id, $schedule->format, $schedule->frequency->periodFor($today));

                $schedule->update(['last_sent_on' => $today]);
                $sent++;
            }
        });

        $this->info("{$sent} relatório(s) agendado(s) enviado(s).");

        return self::SUCCESS;
    }
}
