<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Policy;
use App\Models\User;

/**
 * Autorização das apólices (o nome segue a convenção {Model}Policy do Laravel).
 */
class PolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Policy $policy): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }

    public function update(User $user, Policy $policy): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }

    public function changeStatus(User $user, Policy $policy): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Broker);
    }

    public function delete(User $user, Policy $policy): bool
    {
        return $user->isAdmin();
    }
}
