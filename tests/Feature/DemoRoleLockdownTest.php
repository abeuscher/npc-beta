<?php

use App\Filament\Pages\ImporterPage;
use App\Filament\Pages\MediaFinderPage;
use App\Filament\Pages\Settings\CmsSettingsPage;
use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Filament\Pages\Settings\GeneralSettingsPage;
use App\Filament\Pages\Settings\MailSettingsPage;
use App\Filament\Pages\SiteImportExportPage;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Standing guard for the public, internet-facing /demo/enter auto-login.
 *
 * The `demo` role is an allow-list with a binding deny-list (PermissionSeeder).
 * This file asserts the wall holds in practice — at the real Filament
 * canAccess() gates and the wipe route, not just on the permission vocabulary —
 * so a future permission change cannot silently widen the public demo. If a
 * surface here starts passing, the demo just gained an exfiltration / config /
 * destruction capability and this guard must be re-reviewed deliberately.
 */
beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();

    $this->demo = User::factory()->create(['is_active' => true]);
    $this->demo->assignRole('demo');
});

it('walls the demo role off from every settings page (mail, finance, routing, CMS, API keys live inside these)', function () {
    $this->actingAs($this->demo);

    // API keys for Stripe / QuickBooks / Mailchimp / Resend live inside these
    // settings pages — denying the page denies the keys.
    expect(MailSettingsPage::canAccess())->toBeFalse();     // mail provider + Resend key
    expect(FinanceSettingsPage::canAccess())->toBeFalse();  // Stripe / QuickBooks credentials
    expect(GeneralSettingsPage::canAccess())->toBeFalse();  // routing prefixes / instance config
    expect(CmsSettingsPage::canAccess())->toBeFalse();      // CMS settings + Mailchimp
});

it('walls the demo role off from user and role management', function () {
    $this->actingAs($this->demo);

    expect(UserResource::canAccess())->toBeFalse();
    expect(RoleResource::canAccess())->toBeFalse();
});

it('walls the demo role off from the data import tools', function () {
    $this->actingAs($this->demo);

    expect(ImporterPage::canAccess())->toBeFalse();
});

it('walls the demo role off from site export / Media Finder (bulk real-data exfiltration + media delete)', function () {
    $this->actingAs($this->demo);

    expect(SiteImportExportPage::canAccess())->toBeFalse(); // Export Site = bulk content+media download
    expect(MediaFinderPage::canAccess())->toBeFalse();      // duplicate/unused finder + delete
});

it('walls the demo role off from the scrub-data wipe and data-generator routes (403, super_admin-only)', function () {
    $this->actingAs($this->demo);

    $this->post(route('filament.admin.dev-tools.random-data.wipe'))->assertStatus(403);
    $this->post(route('filament.admin.dev-tools.random-data.store'))->assertStatus(403);
    $this->post(route('filament.admin.dev-tools.random-data.seed-collections'))->assertStatus(403);
});

it('denies the binding deny-list permissions and is never the policy-bypassing super_admin', function () {
    foreach ([
        'view_any_user', 'create_user', 'update_user', 'delete_user',
        'manage_mail_settings', 'manage_financial_settings', 'manage_cms_settings',
        'manage_email_templates', 'manage_custom_fields', 'manage_membership_tiers',
        'edit_theme_scss', 'manage_routing_prefixes', 'manage_donations',
        'import_data', 'review_imports',
    ] as $denied) {
        expect($this->demo->can($denied))->toBeFalse("demo must not have '{$denied}'");
    }

    expect($this->demo->hasRole('super_admin'))->toBeFalse();
    expect($this->demo->isSuperAdmin())->toBeFalse();
});

it('keeps the demo role view-only on navigation menus and without the locked-page edit permission (session 328)', function () {
    // The header/footer link structure lives in NavigationMenu/NavigationItem —
    // a model the page `locked` flag cannot reach — so the demo nav is view-only.
    expect($this->demo->can('view_any_navigation_menu'))->toBeTrue();
    expect($this->demo->can('view_navigation_menu'))->toBeTrue();
    foreach (['create_navigation_menu', 'update_navigation_menu', 'delete_navigation_menu'] as $denied) {
        expect($this->demo->can($denied))->toBeFalse("demo must not have '{$denied}'");
    }

    // …and it must never hold the permission that bypasses the page edit lock.
    expect($this->demo->can('edit_locked_pages'))->toBeFalse();
});

it('grants the intended product-feel width — full CRUD on events and donations (tuned at session 321)', function () {
    // The egress firewall (Stripe/email fail closed) + daily demo:reset baseline
    // backstop these write flows. Widened deliberately; still an allow-list.
    foreach (['view_any_event', 'view_event', 'create_event', 'update_event', 'delete_event'] as $granted) {
        expect($this->demo->can($granted))->toBeTrue("demo should have '{$granted}'");
    }
    foreach (['view_any_donation', 'view_donation', 'create_donation', 'update_donation', 'delete_donation'] as $granted) {
        expect($this->demo->can($granted))->toBeTrue("demo should have '{$granted}'");
    }

    // …but the financial ledger and fund config stay view-only.
    expect($this->demo->can('view_any_transaction'))->toBeTrue();
    expect($this->demo->can('create_transaction'))->toBeFalse();
    expect($this->demo->can('create_fund'))->toBeFalse();
    expect($this->demo->can('create_campaign'))->toBeFalse();
});
