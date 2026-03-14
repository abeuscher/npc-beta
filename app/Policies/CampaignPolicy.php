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
}
