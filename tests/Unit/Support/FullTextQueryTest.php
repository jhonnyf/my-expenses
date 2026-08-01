<?php

namespace Tests\Unit\Support;

use App\Support\FullTextQuery;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FullTextQueryTest extends TestCase
{
    public function test_builds_boolean_expression_with_prefix_per_word(): void
    {
        $this->assertSame('+FEIJAO*', FullTextQuery::toBooleanExpression('feijao'));
    }

    public function test_splits_hyphenated_terms_into_separate_required_words(): void
    {
        $this->assertSame('+COCA* +COLA*', FullTextQuery::toBooleanExpression('Coca-Cola'));
    }

    public function test_uppercases_before_building_expression(): void
    {
        $this->assertSame('+arroz*', mb_strtolower(FullTextQuery::toBooleanExpression('arroz')));
        $this->assertSame(
            FullTextQuery::toBooleanExpression('ARROZ INTEGRAL'),
            FullTextQuery::toBooleanExpression('arroz integral'),
        );
    }

    public function test_drops_tokens_shorter_than_min_length(): void
    {
        $this->assertSame('+ARROZ*', FullTextQuery::toBooleanExpression('de arroz'));
    }

    public function test_returns_null_when_no_token_reaches_min_length(): void
    {
        $this->assertNull(FullTextQuery::toBooleanExpression('ei'));
    }

    public function test_returns_null_for_only_symbols(): void
    {
        $this->assertNull(FullTextQuery::toBooleanExpression('%%%'));
    }

    public function test_build_falls_back_to_null_on_non_mysql_connection(): void
    {
        // A suíte roda em SQLite (ver phpunit.xml) — build() deve sempre cair pro LIKE aqui,
        // mesmo com um termo que geraria uma expressão válida em MySQL.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertNull(FullTextQuery::build('feijao'));
    }
}
