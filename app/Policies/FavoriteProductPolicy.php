<?php

namespace App\Policies;

use App\Models\FavoriteProduct;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FavoriteProductPolicy
{
    /** Favorito de outro usuário responde 404 (403 confirmaria que o id existe). */
    public function interact(User $user, FavoriteProduct $favoriteProduct): Response
    {
        return $user->id === $favoriteProduct->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
