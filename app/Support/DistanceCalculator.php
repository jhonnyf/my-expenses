<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Distância em km entre duas coordenadas (fórmula de Haversine). A expressão SQL só
 * é usada em MySQL (produção) — em qualquer outro driver (ex: SQLite nos testes
 * automatizados) o filtro de raio é ignorado na query, e o cálculo é validado
 * diretamente via kilometersBetween() nos testes, mesmo princípio de
 * App\Support\FullTextQuery pra MATCH...AGAINST.
 */
class DistanceCalculator
{
    private const EARTH_RADIUS_KM = 6371;

    /**
     * O filtro de raio via SQL só roda em MySQL (produção) — a suíte de testes roda
     * em SQLite, que não tem acos/cos/sin/radians nativos, então o filtro de raio é
     * ignorado nesse driver (comportamento validado via kilometersBetween() direto).
     */
    public static function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    public static function kilometersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Monta a expressão SQL (MySQL) que calcula, em km, a distância entre a
     * coluna informada e um ponto fixo (lat/lng do usuário). Uso:
     * ->selectRaw(DistanceCalculator::mysqlHaversineExpression('issuers.latitude', 'issuers.longitude', $lat, $lng).' as distance_km')
     * ->having('distance_km', '<=', 15)
     */
    public static function mysqlHaversineExpression(string $latColumn, string $lngColumn, float $lat, float $lng): string
    {
        $radius = self::EARTH_RADIUS_KM;

        return "({$radius} * acos(least(1, greatest(-1,
            cos(radians({$lat})) * cos(radians({$latColumn})) * cos(radians({$lngColumn}) - radians({$lng}))
            + sin(radians({$lat})) * sin(radians({$latColumn}))
        ))))";
    }
}
