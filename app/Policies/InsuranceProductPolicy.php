<?php

namespace App\Policies;

use App\Models\InsuranceProduct;
use App\Models\User;

class InsuranceProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InsuranceProduct $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InsuranceProduct $product): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, InsuranceProduct $product): bool
    {
        return $user->isAdmin();
    }
}
