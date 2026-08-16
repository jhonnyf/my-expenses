<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ProductAlias;
use App\Support\FullTextQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductAliasService
{
    /**
     * Faz LEFT JOIN com product_aliases pela description do usuário, para
     * que quem chamar possa usar canonicalNameSql() no select/groupBy/where.
     * Assume que a query já tem `invoices_items` (o table name do model
     * InvoiceItem) no FROM/JOIN.
     */
    public function joinCanonicalNames(Builder $query, int $userId): Builder
    {
        return $query->leftJoin('product_aliases', function ($join) use ($userId) {
            $join->on('product_aliases.description', '=', 'invoices_items.description')
                ->where('product_aliases.user_id', '=', $userId);
        });
    }

    /**
     * SQL do nome "efetivo" de um item: o apelido quando existir, senão a
     * description bruta. Requer joinCanonicalNames() aplicado antes.
     */
    public function canonicalNameSql(): string
    {
        return 'COALESCE(product_aliases.canonical_name, invoices_items.description)';
    }

    /**
     * Faz LEFT JOIN com um apelido "da comunidade" pela description do item:
     * um representante (o menor canonical_name, escolha arbitrária mas
     * determinística) entre os apelidos que QUALQUER usuário diferente de
     * $excludeUserId já deu pra essa description — disponível como
     * `community_aliases.canonical_name` pra quem chamar. Complementa
     * joinCanonicalNames() (que só traz o apelido do próprio $excludeUserId);
     * usado pra exibir de quem veio o apelido em itens da comunidade, não
     * pra decidir o valor "oficial" do item.
     */
    public function joinCommunityCanonicalName(Builder $query, int $excludeUserId): Builder
    {
        $communityAliases = ProductAlias::query()
            ->select('description', DB::raw('MIN(canonical_name) as canonical_name'))
            ->where('user_id', '!=', $excludeUserId)
            ->groupBy('description');

        return $query->leftJoinSub(
            $communityAliases,
            'community_aliases',
            'community_aliases.description',
            '=',
            'invoices_items.description'
        );
    }

    /**
     * Anexa o nome canônico (quando existir) a cada InvoiceItem via atributo
     * dinâmico `canonical_name`, sem disparar uma query por item.
     *
     * @param  Collection<int, InvoiceItem>  $items
     */
    public function attachCanonicalNames(Collection $items, int $userId): void
    {
        $descriptions = $items->pluck('description')->unique();

        if ($descriptions->isEmpty()) {
            return;
        }

        $aliases = ProductAlias::where('user_id', $userId)
            ->whereIn('description', $descriptions)
            ->pluck('canonical_name', 'description');

        $items->each(function ($item) use ($aliases) {
            $item->canonical_name = $aliases[$item->description] ?? null;
        });
    }

    public function setAlias(int $userId, string $description, ?string $canonicalName): ?ProductAlias
    {
        $canonicalName = trim((string) $canonicalName);

        if ($canonicalName === '') {
            ProductAlias::where('user_id', $userId)->where('description', $description)->delete();

            return null;
        }

        return ProductAlias::updateOrCreate(
            ['user_id' => $userId, 'description' => $description],
            ['canonical_name' => $canonicalName]
        );
    }

    public function mergeInto(int $userId, string $canonicalName, array $descriptions): void
    {
        foreach ($descriptions as $description) {
            $this->setAlias($userId, $description, $canonicalName);
        }
    }

    /**
     * Só retorna um nome se TODAS as descrições dadas já tiverem alias de
     * outro usuário e todos concordarem no mesmo canonical_name — consenso
     * simples, evita reaproveitar um nome que só um único outro usuário
     * decidiu sozinho (possivelmente errado).
     */
    public function findSharedCanonicalName(array $descriptions, int $excludeUserId): ?string
    {
        $descriptions = array_values(array_unique($descriptions));

        if ($descriptions === []) {
            return null;
        }

        $namesByDescription = ProductAlias::whereIn('description', $descriptions)
            ->where('user_id', '!=', $excludeUserId)
            ->get()
            ->groupBy('description')
            ->map(fn (Collection $group) => $group->pluck('canonical_name')->unique());

        if ($namesByDescription->count() !== count($descriptions)) {
            return null;
        }

        $allNames = $namesByDescription->flatten()->unique();

        return $allNames->count() === 1 ? $allNames->first() : null;
    }

    /**
     * Fecho de subquery correlacionada (EXISTS): casa a busca quando a
     * description bruta do item tem apelido criado por QUALQUER outro
     * usuário batendo com o termo (busca full-text/MySQL). Não altera o
     * nome exibido — esse continua sendo o apelido do próprio buscador via
     * canonicalNameSql(), ou a description bruta na ausência dele; só o
     * critério de busca passa a enxergar apelidos de terceiros.
     */
    public function otherUsersAliasFullTextExists(string $term, int $excludeUserId): \Closure
    {
        return function (QueryBuilder $sub) use ($term, $excludeUserId) {
            $sub->from('product_aliases')
                ->whereColumn('product_aliases.description', 'invoices_items.description')
                ->where('product_aliases.user_id', '!=', $excludeUserId);

            FullTextQuery::matchColumn($sub, 'product_aliases.canonical_name', $term);
        };
    }

    /**
     * Mesma ideia de otherUsersAliasFullTextExists(), mas casando por LIKE —
     * para buscas que não usam FullTextQuery::applyOr() (ex: histórico de
     * preços, restrito a um único usuário e já usa LIKE puro nas colunas).
     */
    public function otherUsersAliasLikeExists(string $term, int $excludeUserId): \Closure
    {
        return function (QueryBuilder $sub) use ($term, $excludeUserId) {
            $sub->from('product_aliases')
                ->whereColumn('product_aliases.description', 'invoices_items.description')
                ->where('product_aliases.user_id', '!=', $excludeUserId)
                ->where('product_aliases.canonical_name', 'like', "%{$term}%");
        };
    }

    /**
     * Descrições que o próprio usuário já comprou mas ainda não apelidou, e
     * que já têm um nome com consenso entre outros usuários — candidatas a
     * reaproveitar de graça, sem IA nem digitação manual.
     *
     * @return array<int, array{description: string, canonical_name: string}>
     */
    public function communitySuggestions(int $userId): array
    {
        $userDescriptions = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select('invoices_items.description')
            ->distinct()
            ->pluck('description');

        if ($userDescriptions->isEmpty()) {
            return [];
        }

        $ownAliasedDescriptions = ProductAlias::where('user_id', $userId)
            ->whereIn('description', $userDescriptions)
            ->pluck('description');

        $unaliasedDescriptions = $userDescriptions->diff($ownAliasedDescriptions)->values();

        if ($unaliasedDescriptions->isEmpty()) {
            return [];
        }

        return ProductAlias::whereIn('description', $unaliasedDescriptions)
            ->where('user_id', '!=', $userId)
            ->get()
            ->groupBy('description')
            ->map(fn (Collection $group) => $group->pluck('canonical_name')->unique())
            ->filter(fn (Collection $names) => $names->count() === 1)
            ->map(fn (Collection $names, string $description) => [
                'description' => $description,
                'canonical_name' => $names->first(),
            ])
            ->values()
            ->take(100)
            ->all();
    }
}
