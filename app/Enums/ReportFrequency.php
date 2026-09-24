<?php

namespace App\Enums;

use Carbon\Carbon;

enum ReportFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Toda segunda-feira (semana anterior)',
            self::Monthly => 'Todo dia 1º (mês anterior)',
        };
    }

    public function isDueOn(Carbon $day): bool
    {
        return match ($this) {
            self::Weekly => $day->isMonday(),
            self::Monthly => $day->day === 1,
        };
    }

    /**
     * Período que o envio de `$day` cobre: a semana (segunda a domingo) ou o mês civil imediatamente anteriores.
     *
     * @return array{start_date: string, end_date: string}
     */
    public function periodFor(Carbon $day): array
    {
        $previous = match ($this) {
            self::Weekly => $day->copy()->subWeek(),
            self::Monthly => $day->copy()->subMonthNoOverflow(),
        };

        // Semana explícita (segunda a domingo): o início da semana do Carbon muda conforme o idioma (pt_BR = domingo).
        return match ($this) {
            self::Weekly => [
                'start_date' => $previous->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'end_date' => $previous->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ],
            self::Monthly => [
                'start_date' => $previous->copy()->startOfMonth()->toDateString(),
                'end_date' => $previous->copy()->endOfMonth()->toDateString(),
            ],
        };
    }

    /** Próximo dia (a partir de amanhã) em que o envio acontece. */
    public function nextRunAfter(Carbon $day): Carbon
    {
        $next = $day->copy()->addDay()->startOfDay();

        return match ($this) {
            self::Weekly => $next->isMonday() ? $next : $next->next(Carbon::MONDAY),
            self::Monthly => $next->day === 1 ? $next : $next->addMonthNoOverflow()->startOfMonth(),
        };
    }
}
