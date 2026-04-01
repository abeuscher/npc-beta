<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_membership');
    }

    public function view(User $user, Membership $membership): bool
    {
        return $user->can('view_membership');
    }

    public function create(User $user): bool
    {
        return $user->can('create_membership');
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->can('update_membership');
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $user->can('delete_membership');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_membership');
    }

    public function restore(User $user, Membership $membership): bool
    {
        return $user->can('delete_membership');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_membership');
    }

    public function forceDelete(User $user, Membership $membership): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
