<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestão de usuários é exclusiva do administrador. Um administrador não pode
 * excluir a própria conta, para que o sistema nunca fique sem acesso
 * administrativo por engano.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }
}
