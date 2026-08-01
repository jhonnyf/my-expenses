<?php

namespace App\Search\Strategies;

use App\Contracts\SearchStrategyInterface;
use App\Models\Issuer;
use App\Support\FullTextQuery;
use Illuminate\Support\Collection;

class IssuerSearchStrategy implements SearchStrategyInterface
{
    public function search(string $query, int $userId): Collection
    {
        $issuerQuery = Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId));

        FullTextQuery::applyOr($issuerQuery, $query, ['name'], ['cnpj']);

        return $issuerQuery
            ->select('id', 'name', 'cnpj', 'city', 'state')
            ->with('nicknameForUser')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'type' => 'issuer',
                'id' => $i->id,
                'title' => $i->display_name,
                'subtitle' => $i->cnpj.' - '.$i->city.'/'.$i->state,
                'url' => route('issuers.detail', $i->id),
            ]);
    }
}
