<?php

namespace App\Policies;

use App\Models\MailingList;
use App\Models\User;

class MailingListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_mailing_list');
    }

    public function view(User $user, MailingList $mailingList): bool
    {
        return $user->can('view_mailing_list');
    }

    public function create(User $user): bool
    {
        return $user->can('create_mailing_list');
    }

    public function update(User $user, MailingList $mailingList): bool
    {
        return $user->can('update_mailing_list');
    }

    public function delete(User $user, MailingList $mailingList): bool
    {
        return $user->can('delete_mailing_list');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_mailing_list');
    }
}
