<?php

namespace Plugins\MemberPortal\Widgets\PortalContactEdit;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;
use Database\Seeders\DemoPortalMemberSeeder;

class PortalContactEditDefinition extends WidgetDefinition
{
    public function template(): string
    {
        return "@include('plugin-member-portal-widgets::PortalContactEdit.template')";
    }

    public function handle(): string
    {
        return 'portal_contact_edit';
    }

    public function label(): string
    {
        return 'Member: Edit Contact Info';
    }

    public function description(): string
    {
        return 'Lets portal members update their name, address, and contact details.';
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
            fields: ['has_contact', 'email', 'household_name', 'city', 'state', 'postal_code', 'country'],
            model: 'portal_member',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
