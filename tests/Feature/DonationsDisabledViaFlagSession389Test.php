<?php

use App\Models\Donation;
use App\Plugins\EmailTemplateRegistry;
use App\Plugins\ImporterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\DonationsDisabledViaFlagTestCase;

uses(DonationsDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 389 (arc D3): runtime-disabled ≡ strip-the-line for the Donations
// vertical (contract surface 1's two-layer model). The installed superset
// keeps its Donations line; PLUGINS_DISABLED=donations subtracts it at the
// filter — the state must reproduce what DonationsPluginRemovalSession389Test
// asserts against the stripped list.

it('keeps the Donations line in the installed superset but does not load the provider', function () {
    expect(config('plugins'))->toContain(\Plugins\Donations\DonationsServiceProvider::class)
        ->and(config('plugin-activation.disabled'))->toBe(['donations'])
        ->and(app()->providerIsLoaded(\Plugins\Donations\DonationsServiceProvider::class))->toBeFalse();
});

it('reproduces the removal mirror: route gone, importer gone, handles gone, observers inert', function () {
    expect(Route::has('donations.checkout'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.donations.index'))->toBeFalse()
        ->and(app(ImporterRegistry::class)->find('donations'))->toBeNull()
        ->and(app(EmailTemplateRegistry::class)->defaultsFor('donation_receipt'))->toBeNull()
        ->and(app('events')->hasListeners('eloquent.updated: ' . Donation::class))->toBeFalse();
});

it('keeps donation data queryable — disabling is never destructive', function () {
    Donation::factory()->create();

    expect(Donation::count())->toBe(1);
});

it('leaves the other plugins running', function () {
    expect(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBeTrue();
});
