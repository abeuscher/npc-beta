<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_collection');
    }

    public function view(User $user, Collection $collection): bool
    {
        return $user->can('view_collection');
    }

    public function create(User $user): bool
    {
        return $user->can('create_collection');
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->can('update_collection');
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->can('delete_collection');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_collection');
    }

    public function restore(User $user, Collection $collection): bool
    {
        return $user->can('delete_collection');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_collection');
    }

    public function forceDelete(User $user, Collection $collection): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
