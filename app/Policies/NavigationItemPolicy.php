<?php

namespace App\Policies;

use App\Models\NavigationItem;
use App\Models\User;

class NavigationItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_navigation_menu');
    }

    public function view(User $user, NavigationItem $navigationItem): bool
    {
        return $user->can('view_navigation_menu');
    }

    public function create(User $user): bool
    {
        return $user->can('create_navigation_menu');
    }

    public function update(User $user, NavigationItem $navigationItem): bool
    {
        return $user->can('update_navigation_menu');
    }

    public function delete(User $user, NavigationItem $navigationItem): bool
    {
        return $user->can('delete_navigation_menu');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_navigation_menu');
    }
}
