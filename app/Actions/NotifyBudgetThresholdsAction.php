<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use App\Services\BudgetService;
use Carbon\Carbon;

class NotifyBudgetThresholdsAction
{
    public function __construct(private readonly BudgetService $budgets) {}

    /**
     * Avisa uma vez por patamar (80% e 100%) e por mês: `alerted_level`/`alerted_month` do orçamento
     * lembram até onde já avisamos. Um novo mês recomeça do zero; gasto que volta a cair não reavisa.
     *
     * @return int quantidade de avisos enviados
     */
    public function execute(User $user): int
    {
        $month = Carbon::now()->format('Y-m');
        $sent = 0;

        foreach ($this->budgets->getBudgetsWithSpending($user->id)['budgets'] as $budget) {
            $level = $this->budgets->alertLevel($budget);
            $alreadyAlerted = $budget->alerted_month === $month ? $budget->alerted_level : 0;

            if ($level <= $alreadyAlerted) {
                continue;
            }

            $user->notify(new BudgetThresholdReached($budget->category?->name, $level, $budget->spent, (float) $budget->amount, $month));

            $budget->update(['alerted_level' => $level, 'alerted_month' => $month]);
            $sent++;
        }

        return $sent;
    }
}
