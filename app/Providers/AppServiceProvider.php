<?php

namespace App\Providers;

use App\Models\SiteSetting;
use App\Services\Media\MediaContentHasher;
use App\Services\Media\MediaRelocator;
use App\Services\Media\MediaSvgSanitizer;
use Filament\Actions\DeleteAction as PageDeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ForceDeleteAction as TableForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // We use Fortify only for its two-factor primitives (the encrypted
        // column shape, TOTP provider, recovery-code generation); enrollment and
        // the login challenge are driven through Filament ourselves. Fortify's
        // own web routes/views would collide with Filament's stock login, so
        // suppress them entirely — the package's classes stay available, no
        // /user/* or /two-factor-* routes are registered. (session 359)
        \Laravel\Fortify\Fortify::ignoreRoutes();

        // The billing-state reader (client billing, contract v2.6.0) is a
        // singleton so its first read is memoized for the life of the request —
        // the pushed document changes rarely, and both the suspension lock screen
        // and the health subcheck read it within a single request.
        $this->app->singleton(\App\Services\Billing\BillingStateReader::class);
    }

    public function boot(): void
    {
        BasePage::formActionsAlignment(Alignment::End);

        // Hash every media's stored original once, as it lands, then relocate it
        // to its content-addressed path. MediaHasBeenAddedEvent fires after the
        // file is copied to the library (at the legacy id path, before the hash
        // exists) on every addMedia* path (uploads, Media::copy(), importer
        // seeding), and before conversions are generated — so hashing then
        // relocating here lands the original and all later conversions under the
        // shared content-addressed directory.
        //
        // SVGs are sanitized first — stripping any executable content before the
        // hash is computed, so the hash and the relocation see the cleaned bytes.
        // This is the single seam that covers every upload path (session 345,
        // Flag 344-A); registerMediaConversions() skips SVG, so the raw original
        // would otherwise be served as a runnable document on direct navigation.
        Event::listen(MediaHasBeenAddedEvent::class, function (MediaHasBeenAddedEvent $event): void {
            app(MediaSvgSanitizer::class)->sanitize($event->media);
            app(MediaContentHasher::class)->persist($event->media);
            app(MediaRelocator::class)->relocate($event->media);
        });

        // Filament's built-in JS pickers (not browser-native)
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native(false));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->native(false));

        // Demo role cannot upload new files (session 329). Disable every Filament
        // FileUpload field with a clear message — the matching UX for the
        // server-side gate (BlockDemoUploads on the Livewire temp-upload endpoint),
        // so the demo sees a disabled field instead of a raw error. Scoped to the
        // role, not demo mode, so an admin/super_admin maintaining the node still
        // uploads. The Vue page-builder + inline-image surfaces are not Filament
        // fields, so they fall back to the endpoint 403.
        FileUpload::configureUsing(function (FileUpload $upload): void {
            if (auth()->user()?->hasRole('demo')) {
                $upload->disabled()->helperText('File uploads are disabled in the demo.');
            }
        });

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
                'site.base_url'      => $settings->get('base_url')?->value      ?? config('app.url'),
                'site.blog_prefix'         => $settings->get('blog_prefix')?->value         ?? 'news',
                'site.donations_prefix'    => $settings->get('donations_prefix')?->value    ?? 'donate',
                'site.description'   => $settings->get('site_description')?->value ?? '',
                'site.timezone'      => $settings->get('timezone')?->value      ?? 'America/Chicago',
                'site.contact_email' => $settings->get('contact_email')?->value ?? '',
                'mail.default'      => $settings->get('mail_driver')?->value      ?? 'log',
                'mail.from.address' => $settings->get('mail_from_address')?->value ?: 'hello@example.com',
                'mail.from.name'    => $settings->get('mail_from_name')?->value    ?: config('app.name'),
                'backup.notifications.mail.from.address' => $settings->get('mail_from_address')?->value ?: 'hello@example.com',
                'backup.notifications.mail.from.name'    => $settings->get('mail_from_name')?->value    ?: config('app.name'),
                'backup.notifications.mail.to'           => $settings->get('contact_email')?->value     ?: 'hello@example.com',
                'services.resend.key'               => $settings->get('resend_api_key')?->value           ?? '',
                'services.mailchimp.api_key'        => $settings->get('mailchimp_api_key')?->value        ?? '',
                'services.mailchimp.server_prefix'  => $settings->get('mailchimp_server_prefix')?->value  ?? '',
                'services.mailchimp.audience_id'    => $settings->get('mailchimp_audience_id')?->value    ?? '',
                'services.mailchimp.webhook_path'   => $settings->get('mailchimp_webhook_path')?->value   ?? 'mailchimp',
                'services.mailchimp.webhook_secret' => $settings->get('mailchimp_webhook_secret')?->value ?? '',
                'services.stripe.publishable_key'    => SiteSetting::get('stripe_publishable_key', ''),
                'services.stripe.secret'             => SiteSetting::get('stripe_secret_key', ''),
                'services.stripe.webhook_secret'     => SiteSetting::get('stripe_webhook_secret', ''),
                'services.quickbooks.key'            => SiteSetting::get('quickbooks_api_key', ''),
                'site.admin_primary_color'           => $settings->get('admin_primary_color')?->value        ?? '#f59e0b',
                'site.admin_secondary_color'         => $settings->get('admin_secondary_color')?->value      ?? '#73bbbb',
            ]);
        } catch (\Throwable $e) {
            // DB not ready (fresh install before migrations) — fall through to defaults
        }

        // Demo mode: force mail to log to prevent outbound email regardless of DB settings.
        if (isDemoMode()) {
            config(['mail.default' => 'log']);
        }
    }
}
