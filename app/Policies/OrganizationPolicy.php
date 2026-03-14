<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_organization');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('view_organization');
    }

    public function create(User $user): bool
    {
        return $user->can('create_organization');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can('update_organization');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->can('delete_organization');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_organization');
    }
}
