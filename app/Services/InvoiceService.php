<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\FullTextQuery;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista de notas do usuário (web e API). A lista mostra todas as notas (inclusive pendentes/não
 * confirmadas); os totais só contam as autorizadas — ver Invoice::booted().
 */
class InvoiceService
{
    public const ALL_TIME_START = Period::ALL_TIME_START;

    private const PER_PAGE = 15;

    /**
     * @param  array{search?: string, start_date?: ?string, end_date?: ?string, issuer_id?: ?int, status?: ?string, sort?: string}  $filters
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $this->filtered($user, $filters)
            ->select('invoices.*')
            ->with('issuer.nicknameForUser')
            ->withCount('items');

        $this->applySort($query, $filters['sort'] ?? 'recent');

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_count: int, total_amount: float, average_ticket: float, daily_average: float, unconfirmed_count: int, delta_pct: ?float}
     */
    public function summaryForUser(User $user, array $filters = []): array
    {
        $totals = $this->authorizedTotals($user, $filters);
        $count = (int) $totals->total_count;
        $amount = (float) $totals->total_amount;

        [$start, $end] = $this->period($filters) ?? [$totals->first_at, Carbon::now()->toDateString()];

        // "Tudo" parte de 2000-01-01: dividir por ~9 mil dias zeraria a média diária.
        if ($start === self::ALL_TIME_START && $totals->first_at) {
            $start = $totals->first_at;
        }

        $days = $start ? Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1 : 0;

        return [
            'total_count' => $count,
            'total_amount' => $amount,
            'average_ticket' => $count > 0 ? $amount / $count : 0.0,
            'daily_average' => $days > 0 ? $amount / $days : 0.0,
            'unconfirmed_count' => $this->filtered($user, $filters)->where('invoices.status', '!=', InvoiceStatus::Authorized)->count(),
            'delta_pct' => $this->deltaPct($user, $filters, $amount),
        ];
    }

    private function authorizedTotals(User $user, array $filters): object
    {
        return $this->filtered($user, $filters)
            ->where('invoices.status', InvoiceStatus::Authorized)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(invoices.total_amount), 0) as total_amount, MIN(invoices.issued_at) as first_at')
            ->first();
    }

    /**
     * Variação do total contra o período imediatamente anterior, de mesma duração. Sem sentido para
     * "Tudo" ou sem período; null também quando o período anterior não teve gasto (evita divisão por zero).
     */
    private function deltaPct(User $user, array $filters, float $current): ?float
    {
        $period = $this->period($filters);

        if ($period === null || $period[0] === self::ALL_TIME_START) {
            return null;
        }

        [$previousStart, $previousEnd] = Period::previous($period[0], $period[1]);

        $previous = (float) $this->authorizedTotals($user, [
            ...$filters,
            'start_date' => $previousStart,
            'end_date' => $previousEnd,
        ])->total_amount;

        return Period::deltaPct($current, $previous);
    }

    /** Um só limite informado completa o outro (início = "Tudo", fim = hoje); nenhum = sem período. */
    private function period(array $filters): ?array
    {
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        if ($start === null && $end === null) {
            return null;
        }

        return [$start ?? self::ALL_TIME_START, $end ?? Carbon::now()->toDateString()];
    }

    private function filtered(User $user, array $filters): Builder
    {
        $query = Invoice::includingUnauthorized()
            ->where('invoices.user_id', $user->id)
            ->leftJoin('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->leftJoin('issuer_nicknames', function ($join) use ($user) {
                $join->on('issuer_nicknames.issuer_id', '=', 'invoices.issuer_id')
                    ->where('issuer_nicknames.user_id', '=', $user->id);
            });

        if ($period = $this->period($filters)) {
            $query->whereDateBetween('invoices.issued_at', $period[0], $period[1]);
        }

        if (($filters['issuer_id'] ?? null) !== null) {
            $query->where('invoices.issuer_id', $filters['issuer_id']);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('invoices.status', $filters['status']);
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            // CNPJ/número aceitam pontuação na busca. Sem FULLTEXT nessas colunas, o LIKE roda só
            // sobre as notas do próprio usuário (já restritas acima).
            $term = preg_match('/^[\d.\/\-\s]+$/', $search) ? preg_replace('/\D/', '', $search) : $search;

            FullTextQuery::applyOr($query, $term, [], ['issuers.name', 'issuers.cnpj', 'issuer_nicknames.nickname', 'invoices.number']);
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('invoices.issued_at'),
            'highest' => $query->orderByDesc('invoices.total_amount')->orderByDesc('invoices.issued_at'),
            'lowest' => $query->orderBy('invoices.total_amount')->orderByDesc('invoices.issued_at'),
            default => $query->orderByDesc('invoices.issued_at'),
        };

        $query->orderByDesc('invoices.id');
    }
}
