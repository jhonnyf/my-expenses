<?php

namespace App\Search\Strategies;

use App\Contracts\SearchStrategyInterface;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class InvoiceSearchStrategy implements SearchStrategyInterface
{
    public function search(string $query, int $userId): Collection
    {
        return Invoice::where('user_id', $userId)
            ->with(['issuer:id,name', 'issuer.nicknameForUser'])
            ->where(fn ($q) => $q
                ->where('number', 'like', "%{$query}%")
                ->orWhere('access_key', 'like', "%{$query}%")
            )
            ->select('id', 'number', 'series', 'issuer_id', 'issued_at', 'total_amount')
            ->orderByDesc('issued_at')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'type' => 'invoice',
                'id' => $i->id,
                'title' => "NFC-e #{$i->number}/{$i->series}",
                'subtitle' => ($i->issuer->display_name ?? '').' - R$ '.number_format($i->total_amount, 2, ',', '.'),
                'url' => route('my-purchases.detail', $i->id),
            ]);
    }
}
