<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

class ShoppingListPolicy
{
    public function interact(User $user, ShoppingList $shoppingList): bool
    {
        return $user->id === $shoppingList->user_id;
    }
}
