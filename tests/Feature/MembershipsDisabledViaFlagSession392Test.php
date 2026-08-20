<?php

use App\Models\Membership;
use App\Plugins\ImporterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\MembershipsDisabledViaFlagTestCase;

uses(MembershipsDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 392 (arc D4): runtime-disabled ≡ strip-the-line for the Memberships
// vertical (contract surface 1's two-layer model). The installed superset
// keeps its Memberships line; PLUGINS_DISABLED=memberships subtracts it at the
// filter — the state must reproduce what MembershipsPluginRemovalSession392Test
// asserts against the stripped list.

it('keeps the Memberships line in the installed superset but does not load the provider', function () {
    expect(config('plugins'))->toContain(\Plugins\Memberships\MembershipsServiceProvider::class)
        ->and(config('plugin-activation.disabled'))->toBe(['memberships'])
        ->and(app()->providerIsLoaded(\Plugins\Memberships\MembershipsServiceProvider::class))->toBeFalse();
});

it('reproduces the removal mirror: route gone, importer gone, observer inert, policy gone', function () {
    expect(Route::has('membership.checkout'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.memberships.index'))->toBeFalse()
        ->and(app(ImporterRegistry::class)->find('memberships'))->toBeNull()
        ->and(app('events')->hasListeners('eloquent.updated: ' . Membership::class))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Gate::getPolicyFor(Membership::class))->toBeNull();
});

it('keeps membership data queryable — disabling is never destructive', function () {
    Membership::factory()->create();

    expect(Membership::count())->toBe(1);
});

it('leaves the other plugins running', function () {
    expect(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBeTrue();
});
