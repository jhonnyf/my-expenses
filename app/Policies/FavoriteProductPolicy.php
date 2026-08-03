<?php

namespace App\Policies;

use App\Models\FavoriteProduct;
use App\Models\User;

class FavoriteProductPolicy
{
    public function interact(User $user, FavoriteProduct $favoriteProduct): bool
    {
        return $user->id === $favoriteProduct->user_id;
    }
}
