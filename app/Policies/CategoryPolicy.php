<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /** Categorias do sistema (sem dono) são visíveis a todos; a de outro usuário responde 404. */
    public function view(User $user, Category $category): Response
    {
        return $category->user_id === null || $category->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Category $category): bool
    {
        return $category->user_id !== null && $user->id === $category->user_id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $category->user_id !== null && $user->id === $category->user_id;
    }
}
