<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ProductAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceHistoryService
{
    public function __construct(private readonly ProductAliasService $aliasService) {}

    /**
     * Busca produtos no histórico de compras do próprio usuário. O termo
     * também casa contra apelidos de produto criados por QUALQUER outro
     * usuário (ver ProductAliasService::otherUsersAliasLikeExists) — o nome
     * "oficial" do resultado (`description`, usado pra abrir o timeline)
     * continua sendo o apelido do próprio usuário, ou a description bruta
     * na ausência dele.
     *
     * `display_description` (só exibição) traz o apelido quando existir,
     * senão a description bruta. `official_description` vem preenchido só
     * quando o front deve mostrar o nome oficial na NF numa segunda linha:
     * quando o único apelido disponível pra esse produto veio de outro
     * usuário — o próprio usuário nunca apelidou, então o nome oficial ajuda
     * a confirmar do que se trata antes de abrir o histórico.
     */
    public function search(string $query, int $userId): Collection
    {
        $itemsQuery = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->leftJoin('product_aliases', function ($join) use ($userId) {
                $join->on('product_aliases.description', '=', 'invoices_items.description')
                    ->where('product_aliases.user_id', '=', $userId);
            });
        $this->aliasService->joinCommunityCanonicalName($itemsQuery, $userId);

        $items = $itemsQuery
            ->where('invoices.user_id', $userId)
            ->where(function ($q) use ($query, $userId) {
                $q->where('invoices_items.description', 'like', "%{$query}%")
                    ->orWhere('product_aliases.canonical_name', 'like', "%{$query}%")
                    ->orWhereExists($this->aliasService->otherUsersAliasLikeExists($query, $userId));
            })
            ->select(
                DB::raw('COALESCE(product_aliases.canonical_name, invoices_items.description) as description'),
                DB::raw('MIN(invoices_items.description) as raw_description'),
                DB::raw('MIN(product_aliases.canonical_name) as own_canonical_name'),
                DB::raw('MIN(community_aliases.canonical_name) as community_canonical_name'),
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MIN(invoices_items.unit_price) as min_price'),
                DB::raw('MAX(invoices_items.unit_price) as max_price'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('MAX(invoices.issued_at) as last_purchased_at')
            )
            ->groupBy(DB::raw('COALESCE(product_aliases.canonical_name, invoices_items.description)'))
            ->orderByDesc('purchase_count')
            ->limit(20)
            ->get();

        $items->each(function (InvoiceItem $item) {
            if ($item->own_canonical_name !== null) {
                $item->display_description = $item->own_canonical_name;
                $item->official_description = null;
            } elseif ($item->community_canonical_name !== null && $item->community_canonical_name !== $item->raw_description) {
                $item->display_description = $item->community_canonical_name;
                $item->official_description = $item->raw_description;
            } else {
                $item->display_description = $item->raw_description;
                $item->official_description = null;
            }

            unset($item->raw_description, $item->own_canonical_name, $item->community_canonical_name);
        });

        return $items;
    }

    public function getTimeline(string $description, int $userId): array
    {
        // $description pode ser um nome canônico (agrupando várias descrições
        // originais unificadas via ProductAlias) ou uma descrição bruta ainda
        // não unificada — nesse caso ela mesma é usada como filtro.
        $matchDescriptions = ProductAlias::where('user_id', $userId)
            ->where('canonical_name', $description)
            ->pluck('description');

        if ($matchDescriptions->isEmpty()) {
            $matchDescriptions = collect([$description]);
        }

        $timeline = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($userId) {
                $join->on('issuer_nicknames.issuer_id', '=', 'issuers.id')
                    ->where('issuer_nicknames.user_id', '=', $userId);
            })
            ->where('invoices.user_id', $userId)
            ->whereIn('invoices_items.description', $matchDescriptions)
            ->select(
                'invoices_items.unit_price',
                'invoices_items.quantity',
                'invoices_items.unit',
                'invoices.issued_at',
                'issuers.id as issuer_id'
            )
            ->selectRaw('COALESCE(issuer_nicknames.nickname, issuers.name) as issuer_name')
            ->orderBy('invoices.issued_at')
            ->limit(100)
            ->get();

        $prices = $timeline->pluck('unit_price')->map(fn ($p) => (float) $p);
        $minPrice = $prices->min() ?? 0;
        $maxPrice = $prices->max() ?? 0;
        $avgPrice = $prices->avg() ?? 0;
        $variationPct = $minPrice > 0 ? (($maxPrice - $minPrice) / $minPrice) * 100 : 0;

        return [
            'timeline' => $timeline,
            'descriptions' => $matchDescriptions->values(),
            'summary' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'avg_price' => round($avgPrice, 2),
                'variation_pct' => round($variationPct, 1),
            ],
        ];
    }
}
