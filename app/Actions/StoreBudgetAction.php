<?php

namespace App\Actions;

use App\Exceptions\ProFeatureRequiredException;
use App\Models\Budget;
use App\Models\User;

/**
 * Centraliza a criação/atualização de orçamento (compartilhada entre web e API)
 * e o limite do plano Grátis: só orçamento geral (sem categoria). Múltiplos
 * orçamentos por categoria são exclusivos do plano Pro.
 */
class StoreBudgetAction
{
    public function execute(User $user, ?int $categoryId, float $amount): Budget
    {
        if ($categoryId !== null && ! $user->isPro()) {
            throw new ProFeatureRequiredException('multiple_budgets');
        }

        return Budget::updateOrCreate(
            ['user_id' => $user->id, 'category_id' => $categoryId],
            ['amount' => $amount]
        )->load('category');
    }
}
