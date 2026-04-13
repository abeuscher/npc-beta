<?php

namespace App\Widgets\PortalLogin;

use App\Widgets\Contracts\WidgetDefinition;

class PortalLoginDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_login';
    }

    public function label(): string
    {
        return 'Member Login Form';
    }

    public function description(): string
    {
        return 'Email and password login form for the member portal.';
    }

    public function category(): array
    {
        return ['portal', 'forms'];
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
