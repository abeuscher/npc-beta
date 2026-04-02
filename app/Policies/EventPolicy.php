<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_event');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->can('view_event');
    }

    public function create(User $user): bool
    {
        return $user->can('create_event');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('update_event');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->can('delete_event');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_event');
    }
}
