<?php

namespace App\Policies;

use App\Models\CmsTag;
use App\Models\User;

class CmsTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cms_tag');
    }

    public function view(User $user, CmsTag $cmsTag): bool
    {
        return $user->can('view_cms_tag');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cms_tag');
    }

    public function update(User $user, CmsTag $cmsTag): bool
    {
        return $user->can('update_cms_tag');
    }

    public function delete(User $user, CmsTag $cmsTag): bool
    {
        return $user->can('delete_cms_tag');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_cms_tag');
    }
}
