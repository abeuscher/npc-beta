<?php

use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Fund;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Setup\SetupChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

function checklistItem(array $items, string $key): array
{
    foreach ($items as $item) {
        if ($item['key'] === $key) {
            return $item;
        }
    }
    test()->fail("Item [{$key}] not found in checklist.");
}

it('returns items ordered: required_to_boot → required_for_feature → optional', function () {
    $items = (new SetupChecklist())->items();

    $categories = array_map(fn ($i) => $i['category'], $items);
    $sortedCopy = $categories;
    usort($sortedCopy, function ($a, $b) {
        $rank = [
            SetupChecklist::CATEGORY_REQUIRED_TO_BOOT     => 0,
            SetupChecklist::CATEGORY_REQUIRED_FOR_FEATURE => 1,
            SetupChecklist::CATEGORY_OPTIONAL             => 2,
        ];
        return $rank[$a] <=> $rank[$b];
    });

    expect($categories)->toBe($sortedCopy);
});

it('returns 14 items', function () {
    expect((new SetupChecklist())->items())->toHaveCount(14);
});

it('checkAdminUser — done when an active super_admin exists', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super_admin');

    $item = checklistItem((new SetupChecklist())->items(), 'admin_user');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE)
        ->and($item['category'])->toBe(SetupChecklist::CATEGORY_REQUIRED_TO_BOOT);
});

it('checkAdminUser — incomplete when no super_admin exists', function () {
    $item = checklistItem((new SetupChecklist())->items(), 'admin_user');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);
});

it('checkAdminUser — incomplete when the only super_admin is inactive', function () {
    $admin = User::factory()->create(['is_active' => false]);
    $admin->assignRole('super_admin');

    $item = checklistItem((new SetupChecklist())->items(), 'admin_user');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);
});

it('checkOrgName — incomplete when site_name is the install default "My Organization"', function () {
    SiteSetting::set('site_name', 'My Organization');

    $item = checklistItem((new SetupChecklist())->items(), 'org_name');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);
});

it('checkOrgName — done when site_name has been changed', function () {
    SiteSetting::set('site_name', 'Acme Foundation');

    $item = checklistItem((new SetupChecklist())->items(), 'org_name');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkSiteUrl — incomplete when base_url contains localhost', function () {
    SiteSetting::set('base_url', 'http://localhost');

    $item = checklistItem((new SetupChecklist())->items(), 'site_url');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);
});

it('checkSiteUrl — done when base_url is a real domain', function () {
    SiteSetting::set('base_url', 'https://acme.org');

    $item = checklistItem((new SetupChecklist())->items(), 'site_url');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkTimezone — always done; description carries the configured value', function () {
    SiteSetting::set('timezone', 'America/New_York');

    $item = checklistItem((new SetupChecklist())->items(), 'timezone');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE)
        ->and($item['description'])->toContain('America/New_York');
});

it('checkMailFromAddress — incomplete when blank, done when filled', function () {
    $item = checklistItem((new SetupChecklist())->items(), 'mail_from_address');
    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);

    SiteSetting::set('mail_from_address', 'hello@acme.org');

    $item = checklistItem((new SetupChecklist())->items(), 'mail_from_address');
    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkMailDriverLive — incomplete when driver is log', function () {
    SiteSetting::set('mail_driver', 'log');

    $item = checklistItem((new SetupChecklist())->items(), 'mail_driver_live');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE)
        ->and($item['category'])->toBe(SetupChecklist::CATEGORY_REQUIRED_FOR_FEATURE);
});

it('checkMailDriverLive — incomplete when driver is resend but no API key', function () {
    SiteSetting::set('mail_driver', 'resend');
    SiteSetting::set('resend_api_key', '');

    $item = checklistItem((new SetupChecklist())->items(), 'mail_driver_live');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);
});

it('checkMailDriverLive — done when driver=resend and API key is present', function () {
    SiteSetting::set('mail_driver', 'resend');
    SiteSetting::set('resend_api_key', 're_test_key');

    $item = checklistItem((new SetupChecklist())->items(), 'mail_driver_live');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkDefaultFund — incomplete when no funds exist', function () {
    $item = checklistItem((new SetupChecklist())->items(), 'default_fund');

    expect($item['status'])->toBe(SetupChecklist::STATUS_INCOMPLETE);
});

it('checkDefaultFund — done when at least one fund exists', function () {
    Fund::factory()->create();

    $item = checklistItem((new SetupChecklist())->items(), 'default_fund');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkStripe — optional when neither key is set', function () {
    $item = checklistItem((new SetupChecklist())->items(), 'stripe');

    expect($item['status'])->toBe(SetupChecklist::STATUS_OPTIONAL)
        ->and($item['category'])->toBe(SetupChecklist::CATEGORY_OPTIONAL);
});

it('checkStripe — done when both publishable and secret keys are set with live publishable key', function () {
    SiteSetting::set('stripe_publishable_key', 'pk_live_abc');
    SiteSetting::set('stripe_secret_key', 'sk_live_xyz');

    $item = checklistItem((new SetupChecklist())->items(), 'stripe');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkStripe — warning when configured against a test-mode publishable key', function () {
    SiteSetting::set('stripe_publishable_key', 'pk_test_abc');
    SiteSetting::set('stripe_secret_key', 'sk_test_xyz');

    $item = checklistItem((new SetupChecklist())->items(), 'stripe');

    expect($item['status'])->toBe(SetupChecklist::STATUS_WARNING)
        ->and($item['message'])->toContain('test-mode');
});

it('checkQuickBooks — optional when no realm id, done when realm id is set', function () {
    $item = checklistItem((new SetupChecklist())->items(), 'quickbooks');
    expect($item['status'])->toBe(SetupChecklist::STATUS_OPTIONAL);

    SiteSetting::set('qb_realm_id', '9341454300000000');

    $item = checklistItem((new SetupChecklist())->items(), 'quickbooks');
    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkMailchimp — done when api key is set', function () {
    SiteSetting::set('mailchimp_api_key', 'abc-us14');

    $item = checklistItem((new SetupChecklist())->items(), 'mailchimp');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkLogo — done when either admin or public logo path is filled', function () {
    SiteSetting::set('admin_logo_path', 'site/logo.png');

    $item = checklistItem((new SetupChecklist())->items(), 'logo');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkLogo — optional when both logo paths are empty', function () {
    $item = checklistItem((new SetupChecklist())->items(), 'logo');

    expect($item['status'])->toBe(SetupChecklist::STATUS_OPTIONAL);
});

it('checkThemeColors — optional when both colors are install defaults', function () {
    SiteSetting::set('admin_primary_color', '#f59e0b');
    SiteSetting::set('admin_secondary_color', '#73bbbb');

    $item = checklistItem((new SetupChecklist())->items(), 'theme_colors');

    expect($item['status'])->toBe(SetupChecklist::STATUS_OPTIONAL);
});

it('checkThemeColors — done when at least one color is customized', function () {
    SiteSetting::set('admin_primary_color', '#0ea5e9');

    $item = checklistItem((new SetupChecklist())->items(), 'theme_colors');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkCustomFields — done when at least one definition exists', function () {
    CustomFieldDef::create([
        'model_type' => 'App\\Models\\Contact',
        'label'      => 'Favourite color',
        'handle'     => 'favourite_color',
        'field_type' => 'text',
        'sort_order' => 0,
    ]);

    $item = checklistItem((new SetupChecklist())->items(), 'custom_fields');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkImportData — done when at least one imported contact exists', function () {
    Contact::factory()->create(['source' => 'import']);

    $item = checklistItem((new SetupChecklist())->items(), 'import_data');

    expect($item['status'])->toBe(SetupChecklist::STATUS_DONE);
});

it('checkImportData — optional when no imported contacts exist', function () {
    Contact::factory()->create(['source' => 'human']);

    $item = checklistItem((new SetupChecklist())->items(), 'import_data');

    expect($item['status'])->toBe(SetupChecklist::STATUS_OPTIONAL);
});

it('isFirstRun — true when installation_completed_at is unset', function () {
    expect((new SetupChecklist())->isFirstRun())->toBeTrue();
});

it('isFirstRun — false once installation_completed_at is set', function () {
    SiteSetting::set('installation_completed_at', now()->toIso8601String());

    expect((new SetupChecklist())->isFirstRun())->toBeFalse();
});

it('markComplete — writes an ISO-shaped timestamp into installation_completed_at', function () {
    $checklist = new SetupChecklist();
    $checklist->markComplete();

    $stored = SiteSetting::get('installation_completed_at');
    expect($stored)->toBeString()
        ->and($checklist->isFirstRun())->toBeFalse();

    expect(\Carbon\Carbon::parse($stored)->isToday())->toBeTrue();
});

it('resetInstallState — nulls installation_completed_at', function () {
    $checklist = new SetupChecklist();
    $checklist->markComplete();
    expect($checklist->isFirstRun())->toBeFalse();

    $checklist->resetInstallState();
    expect($checklist->isFirstRun())->toBeTrue()
        ->and(SiteSetting::get('installation_completed_at'))->toBeEmpty();
});
