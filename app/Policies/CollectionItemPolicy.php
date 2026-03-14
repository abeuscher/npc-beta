<?php

namespace App\Policies;

use App\Models\CollectionItem;
use App\Models\User;

class CollectionItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_collection_item');
    }

    public function view(User $user, CollectionItem $collectionItem): bool
    {
        return $user->can('view_collection_item');
    }

    public function create(User $user): bool
    {
        return $user->can('create_collection_item');
    }

    public function update(User $user, CollectionItem $collectionItem): bool
    {
        return $user->can('update_collection_item');
    }

    public function delete(User $user, CollectionItem $collectionItem): bool
    {
        return $user->can('delete_collection_item');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_collection_item');
    }
}
