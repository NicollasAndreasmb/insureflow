<?php

namespace App\Policies;

use App\Models\Installment;
use App\Models\User;

class InstallmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Installment $installment): bool
    {
        return true;
    }

    public function pay(User $user, Installment $installment): bool
    {
        return true;
    }
}
