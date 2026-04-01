<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_campaign');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('view_campaign');
    }

    public function create(User $user): bool
    {
        return $user->can('create_campaign');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('update_campaign');
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('delete_campaign');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_campaign');
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->can('delete_campaign');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_campaign');
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
