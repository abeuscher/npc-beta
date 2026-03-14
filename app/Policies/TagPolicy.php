<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_tag');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->can('view_tag');
    }

    public function create(User $user): bool
    {
        return $user->can('create_tag');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('update_tag');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('delete_tag');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_tag');
    }
}
