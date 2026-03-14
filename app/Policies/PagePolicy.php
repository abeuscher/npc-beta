<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_page');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('view_page');
    }

    public function create(User $user): bool
    {
        return $user->can('create_page');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('update_page');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('delete_page');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_page');
    }
}
