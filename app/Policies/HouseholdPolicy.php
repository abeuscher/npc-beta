<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_household');
    }

    public function view(User $user, Household $household): bool
    {
        return $user->can('view_household');
    }

    public function create(User $user): bool
    {
        return $user->can('create_household');
    }

    public function update(User $user, Household $household): bool
    {
        return $user->can('update_household');
    }

    public function delete(User $user, Household $household): bool
    {
        return $user->can('delete_household');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_household');
    }
}
