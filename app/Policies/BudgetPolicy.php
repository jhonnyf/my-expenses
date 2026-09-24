<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BudgetPolicy
{
    /** Orçamento de outro usuário responde 404 (403 confirmaria que o id existe). */
    public function delete(User $user, Budget $budget): Response
    {
        return $user->id === $budget->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
