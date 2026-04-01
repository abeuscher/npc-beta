<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_note');
    }

    public function view(User $user, Note $note): bool
    {
        return $user->can('view_note');
    }

    public function create(User $user): bool
    {
        return $user->can('create_note');
    }

    public function update(User $user, Note $note): bool
    {
        return $user->can('update_note');
    }

    public function delete(User $user, Note $note): bool
    {
        return $user->can('delete_note');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_note');
    }

    public function restore(User $user, Note $note): bool
    {
        return $user->can('delete_note');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_note');
    }

    public function forceDelete(User $user, Note $note): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
