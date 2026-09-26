<?php

namespace App\Policies;

use App\Models\Insurer;
use App\Models\User;

class InsurerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Insurer $insurer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Insurer $insurer): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Insurer $insurer): bool
    {
        return $user->isAdmin();
    }
}
