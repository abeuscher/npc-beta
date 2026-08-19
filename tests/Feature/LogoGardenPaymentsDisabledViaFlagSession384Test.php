<?php

use App\Models\WidgetType;
use App\Plugins\CapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\LogoGardenPaymentsDisabledViaFlagTestCase;

uses(LogoGardenPaymentsDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 384 (Plugin Architecture arc P8): the generalization proof — the
// activation filter subtracts any set of handles, not just the one the
// removal mirrors cover. LogoGarden and Payments are disabled by flag while
// Events stays enabled. This is also LogoGarden's first vanish coverage.

it('loads neither disabled provider while Events still loads', function () {
    expect(app()->providerIsLoaded(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBeFalse()
        ->and(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeFalse()
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue();
});

it('keeps all three lines in config(plugins) — the installed superset is untouched', function () {
    expect(config('plugins'))->toHaveCount(3)
        ->and(config('plugin-activation.disabled'))->toBe(['logo-garden', 'payments']);
});

it('reports the payments capability neither present nor enabled, and drops the webhook route', function () {
    $registry = app(CapabilityRegistry::class);

    expect($registry->present('payments'))->toBeFalse()
        ->and($registry->enabled('payments'))->toBeFalse()
        ->and(Route::has('webhooks.stripe'))->toBeFalse();
});

it('drops the logo_garden widget row on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    expect(WidgetType::pluck('handle'))->not->toContain('logo_garden');
});

it('keeps the Events surface fully registered', function () {
    expect(Route::has('events.register'))->toBeTrue()
        ->and(Route::has('portal.events.register'))->toBeTrue()
        ->and(Route::has('api.events.json'))->toBeTrue()
        ->and(collect(app(\Illuminate\Contracts\Console\Kernel::class)->all())->has('events:send-reminders'))->toBeTrue();
});

it('syncs the seven event widget rows with Events enabled', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = WidgetType::pluck('handle');

    foreach ([
        'event_description', 'event_image', 'event_mini_calendar',
        'event_registration', 'events_listing', 'portal_event_registrations',
        'this_weeks_events',
    ] as $handle) {
        expect($handles)->toContain($handle);
    }
});
