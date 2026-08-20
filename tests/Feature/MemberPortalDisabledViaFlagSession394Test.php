<?php

use App\Models\Contact;
use App\Models\PortalAccount;
use App\Plugins\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\MemberPortalDisabledViaFlagTestCase;

uses(MemberPortalDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 394 (arc D5): runtime-disabled ≡ strip-the-line for the Member
// Portal vertical (contract surface 1's two-layer model). The installed
// superset keeps its MemberPortal line; PLUGINS_DISABLED=member-portal
// subtracts it at the filter — the state must reproduce what
// MemberPortalPluginRemovalSession394Test asserts against the stripped list.

it('keeps the MemberPortal line in the installed superset but does not load the provider', function () {
    expect(config('plugins'))->toContain(\Plugins\MemberPortal\MemberPortalServiceProvider::class)
        ->and(config('plugin-activation.disabled'))->toBe(['member-portal'])
        ->and(app()->providerIsLoaded(\Plugins\MemberPortal\MemberPortalServiceProvider::class))->toBeFalse();
});

it('reproduces the removal mirror: routes gone, guard config gone, alias gone, capability withdrawn', function () {
    expect(Route::has('portal.login'))->toBeFalse()
        ->and(Route::has('portal.signup.post'))->toBeFalse()
        ->and(Route::has('portal.account'))->toBeFalse()
        ->and(Route::has('portal.events.register'))->toBeFalse()
        ->and(config('auth.guards.portal'))->toBeNull()
        ->and(config('auth.passwords.portal_accounts'))->toBeNull()
        ->and(app('router')->getMiddleware())->not->toHaveKey('portal.auth')
        ->and(app(CapabilityRegistry::class)->present('member-portal'))->toBeFalse();
});

it('keeps portal account data queryable — disabling is never destructive', function () {
    $contact = Contact::factory()->create();
    PortalAccount::factory()->create(['contact_id' => $contact->id]);

    expect(PortalAccount::count())->toBe(1);
});

it('leaves the other plugins running', function () {
    expect(app()->providerIsLoaded(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Memberships\MembershipsServiceProvider::class))->toBeTrue();
});
