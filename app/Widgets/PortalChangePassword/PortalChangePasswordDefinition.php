<?php

namespace App\Widgets\PortalChangePassword;

use App\Widgets\Contracts\WidgetDefinition;

class PortalChangePasswordDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_change_password';
    }

    public function label(): string
    {
        return 'Member: Change Password';
    }

    public function description(): string
    {
        return 'Password change form for authenticated portal members.';
    }

    public function category(): array
    {
        return ['portal'];
    }

    public function allowedPageTypes(): ?array
    {
        return ['member'];
    }

    public function schema(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return [];
    }
}
