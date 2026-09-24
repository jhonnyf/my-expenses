<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Intervalos de datas (Y-m-d, limites inclusivos) usados pelos filtros de período das telas.
 */
final class Period
{
    /** Sentinela do período "Tudo" (ver partials/_period-filter). */
    public const ALL_TIME_START = '2000-01-01';

    public static function isAllTime(string $start): bool
    {
        return $start === self::ALL_TIME_START;
    }

    public static function days(string $start, string $end): int
    {
        return Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1;
    }

    /**
     * Período imediatamente anterior, de mesma duração.
     *
     * @return array{0: string, 1: string}
     */
    public static function previous(string $start, string $end): array
    {
        $previousEnd = Carbon::parse($start)->startOfDay()->subDay();

        return [
            $previousEnd->copy()->subDays(self::days($start, $end) - 1)->toDateString(),
            $previousEnd->toDateString(),
        ];
    }

    /** Variação percentual; null quando não há base (anterior zero) para comparar. */
    public static function deltaPct(float $current, float $previous): ?float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null;
    }
}
