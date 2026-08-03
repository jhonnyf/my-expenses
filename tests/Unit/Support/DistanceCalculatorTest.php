<?php

namespace Tests\Unit\Support;

use App\Support\DistanceCalculator;
use Tests\TestCase;

class DistanceCalculatorTest extends TestCase
{
    public function test_kilometers_between_same_point_is_zero(): void
    {
        $this->assertEqualsWithDelta(
            0.0,
            DistanceCalculator::kilometersBetween(-25.4284, -49.2733, -25.4284, -49.2733),
            0.001
        );
    }

    public function test_kilometers_between_curitiba_and_sao_paulo(): void
    {
        // Curitiba (-25.4284, -49.2733) -> São Paulo (-23.5505, -46.6333), ~339km em linha reta.
        $distance = DistanceCalculator::kilometersBetween(-25.4284, -49.2733, -23.5505, -46.6333);

        $this->assertEqualsWithDelta(339.05, $distance, 1);
    }

    public function test_mysql_haversine_expression_contains_columns_and_point(): void
    {
        $expression = DistanceCalculator::mysqlHaversineExpression('issuers.latitude', 'issuers.longitude', -25.4284, -49.2733);

        $this->assertStringContainsString('issuers.latitude', $expression);
        $this->assertStringContainsString('issuers.longitude', $expression);
        $this->assertStringContainsString('-25.4284', $expression);
        $this->assertStringContainsString('-49.2733', $expression);
    }

    public function test_is_my_sql_is_false_on_test_sqlite_connection(): void
    {
        // A suíte roda em SQLite (ver phpunit.xml) — o filtro de raio via SQL nunca
        // deve ser considerado ativo aqui, mesmo com a expressão montada corretamente acima.
        $this->assertFalse(DistanceCalculator::isMySql());
    }
}
