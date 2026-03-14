<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_contact');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->can('view_contact');
    }

    public function create(User $user): bool
    {
        return $user->can('create_contact');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('update_contact');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('delete_contact');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_contact');
    }
}
