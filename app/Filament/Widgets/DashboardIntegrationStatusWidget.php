<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardIntegrationStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-integration-status-widget';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    public function getIntegrations(): array
    {
        $integrations = [
            'MailChimp'   => config('services.mailchimp.api_key'),
            'Resend'      => config('services.resend.key'),
            'Stripe'      => config('services.stripe.key'),
            'QuickBooks'  => config('services.quickbooks.key'),
        ];

        return array_filter($integrations, fn ($key) => !empty($key));
    }
}
