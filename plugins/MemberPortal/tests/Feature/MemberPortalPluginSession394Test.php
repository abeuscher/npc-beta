<?php

use App\Plugins\CapabilityRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 394 (Plugin Architecture arc D5): the enabled twin for the carved
// Member Portal vertical. The full composition must be behavior-identical to
// the pre-carve state: same routes, same guard semantics, same widgets —
// plus the new contract surface, the member-portal capability.

it('loads the MemberPortal provider as the sixth plugin', function () {
    expect(config('plugins'))->toContain(\Plugins\MemberPortal\MemberPortalServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\MemberPortal\MemberPortalServiceProvider::class))->toBeTrue();
});

it('declares the member-portal capability — present and enabled', function () {
    $registry = app(CapabilityRegistry::class);

    expect($registry->present('member-portal'))->toBeTrue()
        ->and($registry->enabled('member-portal'))->toBeTrue();
});

it('injects the portal guard, account provider, and password broker into auth config', function () {
    expect(config('auth.guards.portal'))->toBe([
        'driver'   => 'session',
        'provider' => 'portal_accounts',
    ])->and(config('auth.providers.portal_accounts'))->toBe([
        'driver' => 'eloquent',
        'model'  => \App\Models\PortalAccount::class,
    ])->and(config('auth.passwords.portal_accounts'))->toBe([
        'provider' => 'portal_accounts',
        'table'    => 'portal_password_reset_tokens',
        'expire'   => 60,
        'throttle' => 60,
    ]);
});

it('registers the portal.auth middleware alias from the plugin — the first plugin-owned alias', function () {
    $aliases = app('router')->getMiddleware();

    expect($aliases)->toHaveKey('portal.auth')
        ->and($aliases['portal.auth'])->toBe(\Plugins\MemberPortal\Http\Middleware\PortalAuthenticate::class);
});

it('registers every portal route with its pre-carve name', function () {
    foreach ([
        'portal.signup', 'portal.signup.post',
        'portal.login', 'portal.login.post', 'portal.logout',
        'portal.password.request', 'portal.password.sent', 'portal.password.email',
        'portal.password.reset', 'portal.password.update',
        'portal.verification.notice', 'portal.verification.verify',
        'portal.account',
        'portal.account.update-address', 'portal.account.update-password',
        'portal.account.request-email-change', 'portal.account.confirm-email',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("route {$name} should exist");
    }
});

it('keeps the portal GET routes ahead of the page-slug catch-all', function () {
    expect(Route::getRoutes()->getByName('portal.login')->uri())->toBe('system/login')
        ->and(Route::getRoutes()->getByName('portal.signup')->uri())->toBe('system/signup');
});

it('registers all six portal widgets from the plugin provider', function () {
    $registry = app(WidgetRegistry::class);
    $handles  = collect($registry->all())->map(fn ($d) => $d->handle());

    foreach ([
        'portal_signup', 'portal_login', 'portal_contact_edit',
        'portal_change_password', 'portal_forgot_password', 'portal_account_dashboard',
    ] as $handle) {
        expect($handles)->toContain($handle);
    }
});

it('keeps the Events portal registration route — the member-portal capability consumer, full composition', function () {
    expect(Route::has('portal.events.register'))->toBeTrue();
});

it('resolves the traveling views through the plugin namespace', function () {
    expect(view()->exists('plugin-member-portal::verify-email'))->toBeTrue()
        ->and(view()->exists('plugin-member-portal::reset-password'))->toBeTrue()
        ->and(view()->exists('plugin-member-portal::forgot-password-sent'))->toBeTrue()
        ->and(view()->exists('plugin-member-portal::emails.portal-email-change'))->toBeTrue()
        ->and(view()->exists('plugin-member-portal-widgets::PortalLogin.template'))->toBeTrue();
});
