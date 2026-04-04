<?php

namespace App\Filament\Widgets;

use App\Models\SiteSetting;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Http;

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

    /**
     * Returns the build server status: 'connected', 'unreachable', or 'not_configured'.
     */
    public function getBuildServerStatus(): string
    {
        $url = SiteSetting::get('build_server_url', '') ?: config('services.build_server.url');

        if (! $url) {
            return 'not_configured';
        }

        $apiKey = SiteSetting::get('build_server_api_key', '') ?: config('services.build_server.api_key');

        try {
            $response = Http::timeout(5)
                ->withToken($apiKey ?: '')
                ->get(rtrim($url, '/') . '/health');

            return $response->successful() ? 'connected' : 'unreachable';
        } catch (\Throwable) {
            return 'unreachable';
        }
    }
}
