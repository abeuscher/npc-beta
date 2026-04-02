<?php

namespace App\Policies;

use App\Models\NavigationMenu;
use App\Models\User;

class NavigationMenuPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_navigation_menu');
    }

    public function view(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->can('view_navigation_menu');
    }

    public function create(User $user): bool
    {
        return $user->can('create_navigation_menu');
    }

    public function update(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->can('update_navigation_menu');
    }

    public function delete(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->can('delete_navigation_menu');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_navigation_menu');
    }

    public function restore(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->can('delete_navigation_menu');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_navigation_menu');
    }

    public function forceDelete(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
