<?php

namespace App\Widgets\PortalAccountDashboard;

use App\Widgets\Contracts\WidgetDefinition;

class PortalAccountDashboardDefinition extends WidgetDefinition
{
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
}
