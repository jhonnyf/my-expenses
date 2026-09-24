<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Nota de outro usuário responde 404 (não 403): 403 confirmaria que o id existe.
 */
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): Response
    {
        return $this->ownedBy($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): Response
    {
        return $this->ownedBy($user, $invoice);
    }

    private function ownedBy(User $user, Invoice $invoice): Response
    {
        return $user->id === $invoice->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
