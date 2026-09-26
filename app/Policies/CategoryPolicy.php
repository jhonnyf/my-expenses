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

    /** Categoria do sistema é 403 (o id é público); a de outro usuário é 404, como no `view`. */
    public function update(User $user, Category $category): Response
    {
        return $this->owned($user, $category);
    }

    public function delete(User $user, Category $category): Response
    {
        return $this->owned($user, $category);
    }

    private function owned(User $user, Category $category): Response
    {
        return match (true) {
            $category->user_id === null => Response::deny(),
            $category->user_id === $user->id => Response::allow(),
            default => Response::denyAsNotFound(),
        };
    }
}
