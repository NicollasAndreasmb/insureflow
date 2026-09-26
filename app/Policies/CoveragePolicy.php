<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Coverage;
use App\Models\User;

class CoveragePolicy
{
    public function view(User $user, Coverage $coverage): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }

    public function update(User $user, Coverage $coverage): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }

    public function delete(User $user, Coverage $coverage): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }
}
