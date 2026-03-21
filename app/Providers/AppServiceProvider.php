<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        BasePage::formActionsAlignment(Alignment::End);

        try {
            $settings = SiteSetting::all()->keyBy('key');
            config([
                'site.name'          => $settings->get('site_name')?->value    ?? config('app.name'),
                'site.base_url'      => $settings->get('base_url')?->value      ?? 'http://localhost',
                'site.blog_prefix'   => $settings->get('blog_prefix')?->value   ?? 'news',
                'site.description'   => $settings->get('site_description')?->value ?? '',
                'site.timezone'      => $settings->get('timezone')?->value      ?? 'America/Chicago',
                'site.contact_email' => $settings->get('contact_email')?->value ?? '',
                'mail.default'      => $settings->get('mail_driver')?->value      ?? 'log',
                'mail.from.address' => $settings->get('mail_from_address')?->value ?? 'hello@example.com',
                'mail.from.name'    => $settings->get('mail_from_name')?->value    ?? config('app.name'),
                'services.resend.key'               => $settings->get('resend_api_key')?->value           ?? '',
                'services.mailchimp.api_key'        => $settings->get('mailchimp_api_key')?->value        ?? '',
                'services.mailchimp.server_prefix'  => $settings->get('mailchimp_server_prefix')?->value  ?? '',
                'services.mailchimp.audience_id'    => $settings->get('mailchimp_audience_id')?->value    ?? '',
                'services.mailchimp.webhook_path'   => $settings->get('mailchimp_webhook_path')?->value   ?? 'mailchimp',
                'services.mailchimp.webhook_secret' => $settings->get('mailchimp_webhook_secret')?->value ?? '',
                'services.stripe.key'               => $settings->get('stripe_api_key')?->value             ?? '',
                'services.quickbooks.key'            => $settings->get('quickbooks_api_key')?->value         ?? '',
                'site.admin_primary_color'           => $settings->get('admin_primary_color')?->value        ?? '#f59e0b',
            ]);
        } catch (\Throwable $e) {
            // DB not ready (fresh install before migrations) — fall through to defaults
        }
    }
}
