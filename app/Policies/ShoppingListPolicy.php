<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ShoppingListPolicy
{
    /** Lista de outro usuário responde 404 (403 confirmaria que o id existe). */
    public function interact(User $user, ShoppingList $shoppingList): Response
    {
        return $user->id === $shoppingList->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
