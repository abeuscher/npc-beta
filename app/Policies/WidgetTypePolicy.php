<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WidgetType;

class WidgetTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_widget_type');
    }

    public function view(User $user, WidgetType $widgetType): bool
    {
        return $user->can('view_widget_type');
    }

    public function create(User $user): bool
    {
        return $user->can('create_widget_type');
    }

    public function update(User $user, WidgetType $widgetType): bool
    {
        return $user->can('update_widget_type');
    }

    public function delete(User $user, WidgetType $widgetType): bool
    {
        return $user->can('delete_widget_type');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_widget_type');
    }
}
