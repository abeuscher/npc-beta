<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_transaction');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->can('view_transaction');
    }

    public function create(User $user): bool
    {
        return $user->can('create_transaction');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->can('update_transaction');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->can('delete_transaction');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_transaction');
    }
}
