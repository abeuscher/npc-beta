<?php

namespace Plugins\MemberPortal\Widgets\PortalAccountDashboard;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;
use Database\Seeders\DemoPortalMemberSeeder;

class PortalAccountDashboardDefinition extends WidgetDefinition
{
    public function template(): string
    {
        return "@include('plugin-member-portal-widgets::PortalAccountDashboard.template')";
    }

    public function handle(): string
    {
        return 'portal_account_dashboard';
    }

    public function label(): string
    {
        return 'Member: Account Dashboard';
    }

    public function description(): string
    {
        return 'Portal landing page with account overview and quick links.';
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

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['has_contact', 'first_name', 'email', 'in_household', 'household_name'],
            model: 'portal_member',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
