<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Period;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orçamentos são limites mensais. O gasto de um orçamento por categoria soma o `total_price` dos itens
 * da categoria; o do orçamento Geral soma o valor das notas (`total_amount`, já líquido de desconto), o
 * mesmo total das telas de compras e dashboard. Só notas autorizadas contam.
 */
class BudgetService
{
    /** Com menos dias de dados a projeção só amplifica ruído. */
    private const PROJECTION_MIN_DAYS = 3;

    private const LEVEL_WARNING = 80;

    private const LEVEL_EXCEEDED = 100;

    /**
     * @param  ?string  $month  Y-m; null = mês corrente
     * @return array{budgets: Collection<int, Budget>, categories: Collection, summary: array<string, mixed>, month: array<string, mixed>}
     */
    public function getBudgetsWithSpending(int $userId, ?string $month = null): array
    {
        $start = $this->monthStart($month);
        $previousStart = $start->copy()->subMonth();

        $budgets = Budget::where('user_id', $userId)->with('category')->get();

        $current = $this->spending($userId, $start);
        $previous = $this->spending($userId, $previousStart);

        $budgets = $budgets->map(function (Budget $budget) use ($current, $previous, $start) {
            $this->applySpending($budget, $current, $previous);
            $this->applyProjection($budget, $start);

            return $budget;
        })->sortByDesc('percentage')->values();

        return [
            'budgets' => $budgets,
            'categories' => Category::forUser($userId)->orderBy('name')->get(),
            'summary' => $this->summarize($budgets),
            'month' => $this->describeMonth($start),
        ];
    }

    /** Um orçamento com o gasto do mês corrente (resposta de criar/atualizar). */
    public function attachSpending(Budget $budget): Budget
    {
        $start = $this->monthStart(null);

        $this->applySpending($budget, $this->spending($budget->user_id, $start), $this->spending($budget->user_id, $start->copy()->subMonth()));
        $this->applyProjection($budget, $start);

        return $budget;
    }

    /**
     * Patamar de alerta atingido: 100 (estourou), 80 (perto do limite) ou 0.
     */
    public function alertLevel(Budget $budget): int
    {
        return match (true) {
            $budget->percentage >= self::LEVEL_EXCEEDED => self::LEVEL_EXCEEDED,
            $budget->percentage >= self::LEVEL_WARNING => self::LEVEL_WARNING,
            default => 0,
        };
    }

    private function monthStart(?string $month): Carbon
    {
        return $month
            ? Carbon::createFromFormat('!Y-m', $month)->startOfMonth()
            : Carbon::now()->startOfMonth();
    }

    /**
     * @return array{by_category: Collection<int, string>, total: float}
     */
    private function spending(int $userId, Carbon $monthStart): array
    {
        $start = $monthStart->toDateString();
        $end = $monthStart->copy()->endOfMonth()->toDateString();

        return [
            'by_category' => InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
                ->where('invoices.user_id', $userId)
                ->whereDateBetween('invoices.issued_at', $start, $end)
                ->whereNotNull('invoices_items.category_id')
                ->select('invoices_items.category_id', DB::raw('SUM(invoices_items.total_price) as total'))
                ->groupBy('invoices_items.category_id')
                ->pluck('total', 'category_id'),
            'total' => (float) Invoice::where('user_id', $userId)->whereDateBetween('issued_at', $start, $end)->sum('total_amount'),
        ];
    }

    private function spentBy(Budget $budget, array $spending): float
    {
        return $budget->category_id
            ? (float) ($spending['by_category'][$budget->category_id] ?? 0)
            : $spending['total'];
    }

    private function applySpending(Budget $budget, array $current, array $previous): void
    {
        $budget->spent = $this->spentBy($budget, $current);
        $budget->percentage = $budget->amount > 0 ? ($budget->spent / $budget->amount) * 100 : 0.0;
        $budget->remaining = max(0.0, (float) $budget->amount - $budget->spent);

        $budget->previous_spent = $this->spentBy($budget, $previous);
        $budget->delta_pct = Period::deltaPct($budget->spent, $budget->previous_spent);
    }

    /**
     * Projeção linear do mês corrente: gasto médio por dia até hoje × dias do mês. Só faz sentido no mês
     * em andamento; meses passados já estão fechados.
     */
    private function applyProjection(Budget $budget, Carbon $monthStart): void
    {
        $today = Carbon::now();

        if (! $monthStart->isSameMonth($today)) {
            return;
        }

        $daysInMonth = $monthStart->daysInMonth;
        $elapsed = $today->day;

        if ($budget->remaining > 0) {
            $budget->daily_available = round($budget->remaining / ($daysInMonth - $elapsed + 1), 2);
        }

        if ($elapsed < self::PROJECTION_MIN_DAYS || $budget->spent <= 0) {
            return;
        }

        $dailyRate = $budget->spent / $elapsed;
        $budget->projected = round($dailyRate * $daysInMonth, 2);
        $budget->projected_percentage = $budget->amount > 0 ? ($budget->projected / $budget->amount) * 100 : 0.0;

        if ($budget->spent < $budget->amount && $budget->projected > $budget->amount) {
            $day = min($daysInMonth, max($elapsed + 1, (int) ceil($budget->amount / $dailyRate)));
            $budget->exceeds_on = $monthStart->copy()->day($day)->toDateString();
        }
    }

    /**
     * Com orçamento Geral, ele é o total (já inclui o gasto das categorias — somar os demais contaria em
     * dobro); sem ele, o total é a soma dos orçamentos por categoria.
     *
     * @return array{scope: string, total_budgeted: float, total_spent: float, total_remaining: float, over_budget_count: int}
     */
    private function summarize(Collection $budgets): array
    {
        $general = $budgets->first(fn (Budget $budget) => $budget->category_id === null);
        $scope = $general ? 'general' : 'categories';
        $counted = $general ? collect([$general]) : $budgets;

        return [
            'scope' => $scope,
            'total_budgeted' => (float) $counted->sum('amount'),
            'total_spent' => (float) $counted->sum('spent'),
            'total_remaining' => (float) $counted->sum('remaining'),
            'over_budget_count' => $budgets->where('percentage', '>=', self::LEVEL_EXCEEDED)->count(),
        ];
    }

    /**
     * @return array{value: string, label: string, is_current: bool, previous: string, next: ?string}
     */
    private function describeMonth(Carbon $start): array
    {
        $isCurrent = $start->isSameMonth(Carbon::now());

        return [
            'value' => $start->format('Y-m'),
            'label' => $start->translatedFormat('F \d\e Y'),
            'is_current' => $isCurrent,
            'previous' => $start->copy()->subMonth()->format('Y-m'),
            'next' => $isCurrent ? null : $start->copy()->addMonth()->format('Y-m'),
        ];
    }
}
