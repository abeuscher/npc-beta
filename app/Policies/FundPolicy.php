<?php

namespace App\Policies;

use App\Models\Fund;
use App\Models\User;

class FundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_fund');
    }

    public function view(User $user, Fund $fund): bool
    {
        return $user->can('view_fund');
    }

    public function create(User $user): bool
    {
        return $user->can('create_fund');
    }

    public function update(User $user, Fund $fund): bool
    {
        return $user->can('update_fund');
    }

    public function delete(User $user, Fund $fund): bool
    {
        return $user->can('delete_fund');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_fund');
    }
}
