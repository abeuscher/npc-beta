<?php

namespace App\Widgets\PortalForgotPassword;

use App\Widgets\Contracts\WidgetDefinition;

class PortalForgotPasswordDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_forgot_password';
    }

    public function label(): string
    {
        return 'Member: Forgot Password Form';
    }

    public function description(): string
    {
        return 'Sends a password reset link to the member\'s email address.';
    }

    public function category(): array
    {
        return ['portal', 'forms'];
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
