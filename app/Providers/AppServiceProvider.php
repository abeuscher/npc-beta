<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Membership;
use App\Models\SiteSetting;
use App\Observers\ContactObserver;
use App\Observers\DonationObserver;
use App\Observers\MembershipObserver;
use Filament\Actions\DeleteAction as PageDeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ForceDeleteAction as TableForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
        Membership::observe(MembershipObserver::class);
        Donation::observe(DonationObserver::class);

        BasePage::formActionsAlignment(Alignment::End);

        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native());
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->native());

        $singleDescription = fn (object $record): string =>
            'You are about to permanently delete this ' .
            strtolower(class_basename($record)) .
            '. This cannot be undone.';

        $bulkDescription = function (\Illuminate\Support\Collection $records): string {
            $count = $records->count();
            $label = $records->isNotEmpty()
                ? Str::plural(strtolower(class_basename($records->first())), $count)
                : 'records';
            return "You are about to permanently delete {$count} {$label}. This cannot be undone.";
        };

        TableDeleteAction::configureUsing(fn ($action) => $action->modalDescription($singleDescription));
        TableForceDeleteAction::configureUsing(fn ($action) => $action->modalDescription($singleDescription));
        PageDeleteAction::configureUsing(fn ($action) => $action->modalDescription($singleDescription));
        DeleteBulkAction::configureUsing(fn ($action) => $action->modalDescription($bulkDescription));
        ForceDeleteBulkAction::configureUsing(fn ($action) => $action->modalDescription($bulkDescription));

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
                'mail.from.address' => $settings->get('mail_from_address')?->value ?: 'hello@example.com',
                'mail.from.name'    => $settings->get('mail_from_name')?->value    ?: config('app.name'),
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
