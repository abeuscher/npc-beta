<?php

namespace App\Services\Setup;

use App\Filament\Pages\ImporterPage;
use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Filament\Pages\Settings\GeneralSettingsPage;
use App\Filament\Pages\Settings\MailSettingsPage;
use App\Filament\Resources\CustomFieldDefResource;
use App\Filament\Resources\FundResource;
use App\Filament\Resources\UserResource;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Fund;
use App\Models\SiteSetting;
use App\Models\User;

class SetupChecklist
{
    public const CATEGORY_REQUIRED_TO_BOOT     = 'required_to_boot';
    public const CATEGORY_REQUIRED_FOR_FEATURE = 'required_for_feature';
    public const CATEGORY_OPTIONAL             = 'optional';

    public const STATUS_DONE       = 'done';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_OPTIONAL   = 'optional';
    public const STATUS_WARNING    = 'warning';

    public function items(): array
    {
        return [
            $this->checkAdminUser(),
            $this->checkOrgName(),
            $this->checkSiteUrl(),
            $this->checkTimezone(),
            $this->checkMailFromAddress(),
            $this->checkMailDriverLive(),
            $this->checkDefaultFund(),
            $this->checkStripe(),
            $this->checkQuickBooks(),
            $this->checkMailchimp(),
            $this->checkLogo(),
            $this->checkThemeColors(),
            $this->checkCustomFields(),
            $this->checkImportData(),
        ];
    }

    public function isFirstRun(): bool
    {
        return ! filled(SiteSetting::get('installation_completed_at'));
    }

    public function markComplete(): void
    {
        SiteSetting::set('installation_completed_at', now()->toIso8601String());
    }

    public function resetInstallState(): void
    {
        SiteSetting::set('installation_completed_at', null);
    }

    private function checkAdminUser(): array
    {
        $exists = User::role('super_admin')->where('is_active', true)->exists();

        return [
            'key'           => 'admin_user',
            'title'         => 'Active super-admin user',
            'description'   => 'At least one active user with the super-admin role can sign in to the admin panel.',
            'category'      => self::CATEGORY_REQUIRED_TO_BOOT,
            'status'        => $exists ? self::STATUS_DONE : self::STATUS_INCOMPLETE,
            'configure_url' => UserResource::getUrl('index'),
            'message'       => null,
        ];
    }

    private function checkOrgName(): array
    {
        $name = SiteSetting::get('site_name', '');
        $isDefault = $name === 'My Organization' || ! filled($name);

        return [
            'key'           => 'org_name',
            'title'         => 'Organization name',
            'description'   => 'Your organization name appears in emails, donation receipts, and the public site header.',
            'category'      => self::CATEGORY_REQUIRED_TO_BOOT,
            'status'        => $isDefault ? self::STATUS_INCOMPLETE : self::STATUS_DONE,
            'configure_url' => CmsSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkSiteUrl(): array
    {
        $url = (string) SiteSetting::get('base_url', '');
        $isLocal = ! filled($url) || str_contains($url, 'localhost');

        return [
            'key'           => 'site_url',
            'title'         => 'Site URL',
            'description'   => 'The public URL of this installation, used to generate absolute links in emails and exports.',
            'category'      => self::CATEGORY_REQUIRED_TO_BOOT,
            'status'        => $isLocal ? self::STATUS_INCOMPLETE : self::STATUS_DONE,
            'configure_url' => GeneralSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkTimezone(): array
    {
        $tz = (string) SiteSetting::get('timezone', 'America/Chicago');

        return [
            'key'           => 'timezone',
            'title'         => 'Timezone',
            'description'   => "Currently set to {$tz}. All event times, donation timestamps, and scheduled emails render against this zone.",
            'category'      => self::CATEGORY_REQUIRED_TO_BOOT,
            'status'        => self::STATUS_DONE,
            'configure_url' => CmsSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkMailFromAddress(): array
    {
        $address = (string) SiteSetting::get('mail_from_address', '');

        return [
            'key'           => 'mail_from_address',
            'title'         => 'Mail from-address',
            'description'   => 'The address transactional emails (receipts, registrations, password resets) are sent from.',
            'category'      => self::CATEGORY_REQUIRED_TO_BOOT,
            'status'        => filled($address) ? self::STATUS_DONE : self::STATUS_INCOMPLETE,
            'configure_url' => MailSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkMailDriverLive(): array
    {
        $driver = (string) SiteSetting::get('mail_driver', 'log');
        $apiKey = (string) SiteSetting::get('resend_api_key', '');
        $live   = $driver === 'resend' && filled($apiKey);

        return [
            'key'           => 'mail_driver_live',
            'title'         => 'Live email sending',
            'description'   => 'Mail driver is set to Resend with a saved API key, so registration confirmations and donation receipts actually deliver.',
            'category'      => self::CATEGORY_REQUIRED_FOR_FEATURE,
            'status'        => $live ? self::STATUS_DONE : self::STATUS_INCOMPLETE,
            'configure_url' => MailSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkDefaultFund(): array
    {
        $exists = Fund::query()->exists();

        return [
            'key'           => 'default_fund',
            'title'         => 'At least one fund',
            'description'   => 'Donation imports and the public donation form need a fund to attribute incoming gifts to.',
            'category'      => self::CATEGORY_REQUIRED_FOR_FEATURE,
            'status'        => $exists ? self::STATUS_DONE : self::STATUS_INCOMPLETE,
            'configure_url' => FundResource::getUrl('index'),
            'message'       => null,
        ];
    }

    private function checkStripe(): array
    {
        $publishable = (string) SiteSetting::get('stripe_publishable_key', '');
        $secret      = (string) SiteSetting::get('stripe_secret_key', '');
        $configured  = filled($publishable) && filled($secret);

        $status  = $configured ? self::STATUS_DONE : self::STATUS_OPTIONAL;
        $message = null;

        if ($configured && str_starts_with($publishable, 'pk_test_')) {
            $status  = self::STATUS_WARNING;
            $message = 'Stripe is configured against a test-mode key (pk_test_…). Switch to a live key before accepting real donations.';
        }

        return [
            'key'           => 'stripe',
            'title'         => 'Stripe payments',
            'description'   => 'Required to accept online donations, event tickets, and product purchases through the public site.',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $status,
            'configure_url' => FinanceSettingsPage::getUrl(),
            'message'       => $message,
        ];
    }

    private function checkQuickBooks(): array
    {
        $connected = filled(SiteSetting::get('qb_realm_id', ''));

        return [
            'key'           => 'quickbooks',
            'title'         => 'QuickBooks Online',
            'description'   => 'Connect QuickBooks to sync donations and product sales to your accounting deposit accounts.',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $connected ? self::STATUS_DONE : self::STATUS_OPTIONAL,
            'configure_url' => FinanceSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkMailchimp(): array
    {
        $configured = filled(SiteSetting::get('mailchimp_api_key', ''));

        return [
            'key'           => 'mailchimp',
            'title'         => 'MailChimp sync',
            'description'   => 'Push contacts to a MailChimp audience for newsletter sends.',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $configured ? self::STATUS_DONE : self::STATUS_OPTIONAL,
            'configure_url' => MailSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkLogo(): array
    {
        $admin  = (string) SiteSetting::get('admin_logo_path', '');
        $public = (string) SiteSetting::get('logo_path', '');
        $any    = filled($admin) || filled($public);

        return [
            'key'           => 'logo',
            'title'         => 'Logo uploaded',
            'description'   => 'A logo for the admin header and the public site chrome.',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $any ? self::STATUS_DONE : self::STATUS_OPTIONAL,
            'configure_url' => GeneralSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkThemeColors(): array
    {
        $primary   = (string) SiteSetting::get('admin_primary_color', '#f59e0b');
        $secondary = (string) SiteSetting::get('admin_secondary_color', '#73bbbb');
        $customized = $primary !== '#f59e0b' || $secondary !== '#73bbbb';

        return [
            'key'           => 'theme_colors',
            'title'         => 'Admin theme colors',
            'description'   => 'Primary and secondary accent colors for the admin panel chrome.',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $customized ? self::STATUS_DONE : self::STATUS_OPTIONAL,
            'configure_url' => GeneralSettingsPage::getUrl(),
            'message'       => null,
        ];
    }

    private function checkCustomFields(): array
    {
        $exists = CustomFieldDef::query()->exists();

        return [
            'key'           => 'custom_fields',
            'title'         => 'Custom fields defined',
            'description'   => 'Add custom fields to capture organization-specific data on contacts, events, and other records.',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $exists ? self::STATUS_DONE : self::STATUS_OPTIONAL,
            'configure_url' => CustomFieldDefResource::getUrl('index'),
            'message'       => null,
        ];
    }

    private function checkImportData(): array
    {
        $imported = Contact::query()->where('source', 'import')->exists();

        return [
            'key'           => 'import_data',
            'title'         => 'Contacts imported',
            'description'   => 'Bring contact data in from a previous CRM (Wild Apricot, Neon, Salesforce, or a CSV export).',
            'category'      => self::CATEGORY_OPTIONAL,
            'status'        => $imported ? self::STATUS_DONE : self::STATUS_OPTIONAL,
            'configure_url' => ImporterPage::getUrl(),
            'message'       => null,
        ];
    }
}
