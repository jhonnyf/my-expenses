<?php

namespace App\Search\Strategies;

use App\Contracts\SearchStrategyInterface;
use App\Models\Issuer;
use Illuminate\Support\Collection;

class IssuerSearchStrategy implements SearchStrategyInterface
{
    public function search(string $query, int $userId): Collection
    {
        return Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId))
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$query}%")
                ->orWhere('cnpj', 'like', "%{$query}%")
            )
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
