<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Carbon;

class AccountService
{
    /**
     * Números da conta (só notas visíveis pelo escopo padrão de Invoice: autorizadas).
     *
     * @return array{total_invoices: int, total_items: int, total_spent: float, member_since: Carbon}
     */
    public function stats(User $user): array
    {
        $totals = $user->invoices()
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total_amount), 0) as spent')
            ->toBase()
            ->first();

        return [
            'total_invoices' => (int) $totals->invoice_count,
            'total_items' => InvoiceItem::whereIn('invoice_id', $user->invoices()->select('invoices.id'))->count(),
            'total_spent' => (float) $totals->spent,
            'member_since' => $user->created_at,
        ];
    }
}
