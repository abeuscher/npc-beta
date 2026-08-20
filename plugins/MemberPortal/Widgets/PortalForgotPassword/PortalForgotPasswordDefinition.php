<?php

namespace Plugins\MemberPortal\Widgets\PortalForgotPassword;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;

class PortalForgotPasswordDefinition extends WidgetDefinition
{
    public function template(): string
    {
        return "@include('plugin-member-portal-widgets::PortalForgotPassword.template')";
    }

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

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_PAGE_CONTEXT,
            fields: ['site_name'],
        );
    }
}
