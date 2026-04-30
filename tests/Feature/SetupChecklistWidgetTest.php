<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
});

function actingAsSetupSuperAdmin(): User
{
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super_admin');
    test()->actingAs($admin);

    return $admin;
}

it('widget template renders empty for non-super-admin user', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    $rendered = view('widgets::SetupChecklist.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect(trim($rendered))->toBe('');
});

it('widget template renders empty for unauthenticated visitor', function () {
    $rendered = view('widgets::SetupChecklist.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect(trim($rendered))->toBe('');
});

it('widget template — first-run mode shows every checklist item under its category band', function () {
    actingAsSetupSuperAdmin();

    SiteSetting::set('installation_completed_at', null);

    $rendered = view('widgets::SetupChecklist.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect($rendered)
        ->toContain('Setup Checklist')
        ->toContain('Required to launch')
        ->toContain('Required for specific features')
        ->toContain('Optional')
        ->toContain('Active super-admin user')
        ->toContain('Mail from-address')
        ->toContain('At least one fund')
        ->toContain('Stripe payments')
        ->toContain('Mark setup complete');
});

it('widget template — first-run mode renders the mark-complete form, not the reset form', function () {
    actingAsSetupSuperAdmin();
    SiteSetting::set('installation_completed_at', null);

    $rendered = view('widgets::SetupChecklist.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect($rendered)
        ->toContain(route('filament.admin.setup-checklist.mark-complete'))
        ->and($rendered)->not->toContain(route('filament.admin.setup-checklist.reset'));
});

it('widget template — health-check mode hides done items and shows the reset form', function () {
    $admin = actingAsSetupSuperAdmin();
    SiteSetting::set('installation_completed_at', now()->toIso8601String());

    $rendered = view('widgets::SetupChecklist.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    // Active super_admin exists ⇒ admin_user check is done ⇒ should be hidden in health-check mode.
    expect($rendered)
        ->not->toContain('Active super-admin user');

    // Reset action should be on the page; mark-complete should not.
    expect($rendered)
        ->toContain(route('filament.admin.setup-checklist.reset'))
        ->and($rendered)->not->toContain(route('filament.admin.setup-checklist.mark-complete'));

    // Items needing attention still surface (mail from-address is empty).
    expect($rendered)->toContain('Mail from-address');
});

it('widget template — health-check mode shows the all-clear copy when nothing needs attention', function () {
    actingAsSetupSuperAdmin();

    SiteSetting::set('installation_completed_at', now()->toIso8601String());
    SiteSetting::set('site_name', 'Acme');
    SiteSetting::set('base_url', 'https://acme.org');
    SiteSetting::set('mail_from_address', 'hello@acme.org');
    SiteSetting::set('mail_driver', 'resend');
    SiteSetting::set('resend_api_key', 're_test');
    \App\Models\Fund::factory()->create();
    SiteSetting::set('stripe_publishable_key', 'pk_live_x');
    SiteSetting::set('stripe_secret_key', 'sk_live_y');
    SiteSetting::set('qb_realm_id', '9341454300000000');
    SiteSetting::set('mailchimp_api_key', 'abc-us14');
    SiteSetting::set('admin_logo_path', 'site/logo.png');
    SiteSetting::set('admin_primary_color', '#0ea5e9');
    \App\Models\CustomFieldDef::create([
        'model_type' => 'App\\Models\\Contact',
        'label'      => 'X',
        'handle'     => 'x',
        'field_type' => 'text',
        'sort_order' => 0,
    ]);
    \App\Models\Contact::factory()->create(['source' => 'import']);

    $rendered = view('widgets::SetupChecklist.template')
        ->with('errors', new \Illuminate\Support\ViewErrorBag())
        ->render();

    expect($rendered)->toContain('All items are configured.');
});

it('mark-complete route — non-super-admin gets 403', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    $response = $this->post(route('filament.admin.setup-checklist.mark-complete'));

    $response->assertForbidden();
    expect(SiteSetting::get('installation_completed_at'))->toBeEmpty();
});

it('mark-complete route — unauthenticated request is redirected to login', function () {
    $response = $this->post(route('filament.admin.setup-checklist.mark-complete'));

    $response->assertRedirect();
    expect(SiteSetting::get('installation_completed_at'))->toBeEmpty();
});

it('mark-complete route — super-admin sets installation_completed_at to a valid ISO timestamp', function () {
    actingAsSetupSuperAdmin();

    $response = $this->post(route('filament.admin.setup-checklist.mark-complete'));

    $response->assertRedirect();
    $response->assertSessionHas('setup_checklist_status');

    $stored = SiteSetting::get('installation_completed_at');
    expect($stored)->toBeString()
        ->and(\Carbon\Carbon::parse($stored)->isToday())->toBeTrue();
});

it('reset route — non-super-admin gets 403', function () {
    $user = User::factory()->create();
    test()->actingAs($user);
    SiteSetting::set('installation_completed_at', now()->toIso8601String());

    $response = $this->post(route('filament.admin.setup-checklist.reset'));

    $response->assertForbidden();
    expect(SiteSetting::get('installation_completed_at'))->not->toBeEmpty();
});

it('reset route — super-admin nulls installation_completed_at', function () {
    actingAsSetupSuperAdmin();
    SiteSetting::set('installation_completed_at', now()->toIso8601String());

    $response = $this->post(route('filament.admin.setup-checklist.reset'));

    $response->assertRedirect();
    $response->assertSessionHas('setup_checklist_status');
    expect(SiteSetting::get('installation_completed_at'))->toBeEmpty();
});

it('end-to-end — mark complete, then reset, returns the widget to first-run mode', function () {
    actingAsSetupSuperAdmin();

    $this->post(route('filament.admin.setup-checklist.mark-complete'))->assertRedirect();
    expect(app(\App\Services\Setup\SetupChecklist::class)->isFirstRun())->toBeFalse();

    $this->post(route('filament.admin.setup-checklist.reset'))->assertRedirect();
    expect(app(\App\Services\Setup\SetupChecklist::class)->isFirstRun())->toBeTrue();
});
