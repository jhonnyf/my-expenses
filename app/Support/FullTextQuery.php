<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * LIKE '%termo%' nunca usa índice (wildcard à esquerda) — cada busca escaneia a
 * tabela inteira. Esta classe monta buscas MATCH...AGAINST (FULLTEXT, boolean mode,
 * por prefixo de palavra) nas colunas que têm índice FULLTEXT, com fallback pra LIKE
 * quando a query não gera nenhum termo útil (ex: só símbolos, ou muito curta pro
 * innodb_ft_min_token_size padrão) — preserva o comportamento atual nesses casos.
 */
class FullTextQuery
{
    private const MIN_TOKEN_LENGTH = 3;

    public static function build(string $query): ?string
    {
        // MATCH...AGAINST é sintaxe MySQL — em qualquer outro driver (ex: SQLite nos
        // testes automatizados) não existe índice FULLTEXT pra usar, então cai pro LIKE.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return null;
        }

        return self::toBooleanExpression($query);
    }

    /**
     * Monta a expressão boolean-mode em si, sem depender do driver de conexão —
     * separado de build() só pra ser testável isoladamente (a suíte roda em SQLite).
     */
    public static function toBooleanExpression(string $query): ?string
    {
        $tokens = array_filter(
            ProductNameNormalizer::tokenize($query),
            fn (string $token) => mb_strlen($token) >= self::MIN_TOKEN_LENGTH,
        );

        if ($tokens === []) {
            return null;
        }

        return implode(' ', array_map(fn (string $token) => "+{$token}*", $tokens));
    }

    /**
     * Aplica OR entre colunas de busca textual dentro de um grupo isolado (não
     * interfere em outros where()/orWhere() já encadeados na query). Colunas com
     * índice FULLTEXT usam MATCH...AGAINST; as demais (ex: CNPJ, código — não se
     * beneficiam de busca por palavra) continuam em LIKE.
     *
     * @param  list<string>  $fullTextColumns
     * @param  list<string>  $likeColumns
     */
    public static function applyOr(Builder $query, string $term, array $fullTextColumns, array $likeColumns = []): Builder
    {
        $booleanQuery = self::build($term);

        return $query->where(function (Builder $q) use ($term, $booleanQuery, $fullTextColumns, $likeColumns) {
            foreach ($fullTextColumns as $column) {
                if ($booleanQuery !== null) {
                    $q->orWhereRaw("MATCH({$column}) AGAINST(? IN BOOLEAN MODE)", [$booleanQuery]);
                } else {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            }

            foreach ($likeColumns as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }
}
