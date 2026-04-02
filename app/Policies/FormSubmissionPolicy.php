<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\User;

class FormSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_form_submission');
    }

    public function view(User $user, FormSubmission $submission): bool
    {
        return $user->can('view_form_submission');
    }

    public function delete(User $user, FormSubmission $submission): bool
    {
        return $user->can('delete_form_submission');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_form_submission');
    }

    public function restore(User $user, FormSubmission $submission): bool
    {
        return $user->can('delete_form_submission');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_form_submission');
    }

    public function forceDelete(User $user, FormSubmission $submission): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
