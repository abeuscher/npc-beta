<?php

namespace Plugins\MemberPortal\Widgets\PortalChangePassword;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;
use Database\Seeders\DemoPortalMemberSeeder;

class PortalChangePasswordDefinition extends WidgetDefinition
{
    public function template(): string
    {
        return "@include('plugin-member-portal-widgets::PortalChangePassword.template')";
    }

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

    public function demoContext(): ?array
    {
        return [
            'guard'  => 'portal',
            'seeder' => DemoPortalMemberSeeder::class,
            'login'  => DemoPortalMemberSeeder::ACCOUNT_EMAIL,
        ];
    }

    public function assets(): array
    {
        return ['js' => ['plugins/MemberPortal/Widgets/PortalChangePassword/script.js']];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['id'],
            model: 'portal_member',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
