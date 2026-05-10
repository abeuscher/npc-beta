<?php

namespace App\Policies;

use App\Models\DonationCredit;
use App\Models\User;

class DonationCreditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_donation');
    }

    public function view(User $user, DonationCredit $credit): bool
    {
        return $user->can('view_donation');
    }

    public function create(User $user): bool
    {
        return $user->can('update_donation');
    }

    public function update(User $user, DonationCredit $credit): bool
    {
        return $user->can('update_donation');
    }

    public function delete(User $user, DonationCredit $credit): bool
    {
        return $user->can('update_donation');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_donation');
    }
}
