<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\EventsDisabledViaFlagTestCase;

uses(EventsDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 384 (Plugin Architecture arc P8): runtime-disabled ≡ strip-the-line.
// The application boots with Events disabled via the per-install activation
// flag — config('plugins') untouched, the filter doing the subtraction — and
// must land in exactly the state the removal mirror
// (EventsPluginRemovalSession381Test) asserts. Trimmed to the load-bearing
// vanish points that prove the FILTER; full vanish depth stays pinned by the
// existing mirror.

it('keeps the Events line in config(plugins) — the installed superset is untouched', function () {
    expect(config('plugins'))->toContain(\Plugins\Events\EventsServiceProvider::class)
        ->and(config('plugin-activation.disabled'))->toBe(['events']);
});

it('does not load the Events provider', function () {
    expect(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeFalse();
});

it('drops all three public event routes', function () {
    expect(Route::has('events.register'))->toBeFalse()
        ->and(Route::has('portal.events.register'))->toBeFalse()
        ->and(Route::has('api.events.json'))->toBeFalse();
});

it('drops the admin event resource routes', function () {
    expect(Route::has('filament.admin.resources.events.index'))->toBeFalse()
        ->and(Route::has('filament.admin.pages.import-events-page'))->toBeFalse();
});

it('schedules no reminders job and registers no reminders command', function () {
    $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'events:send-reminders'));

    expect($scheduled)->toHaveCount(0)
        ->and(collect(app(\Illuminate\Contracts\Console\Kernel::class)->all())->has('events:send-reminders'))->toBeFalse();
});

it('drops the seven event widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = WidgetType::pluck('handle');

    foreach ([
        'event_description', 'event_image', 'event_mini_calendar',
        'event_registration', 'events_listing', 'portal_event_registrations',
        'this_weeks_events',
    ] as $handle) {
        expect($handles)->not->toContain($handle);
    }
});

it('keeps event data queryable — disabling is never destructive', function () {
    $event = Event::factory()->create(['status' => 'published']);
    EventRegistration::factory()->create(['event_id' => $event->id, 'email' => 'kept@example.com']);

    expect(Event::count())->toBe(1)
        ->and(EventRegistration::where('email', 'kept@example.com')->exists())->toBeTrue();
});

it('leaves the other plugins untouched', function () {
    expect(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(app()->providerIsLoaded(\Plugins\LogoGarden\LogoGardenServiceProvider::class))->toBeTrue()
        ->and(Route::has('webhooks.stripe'))->toBeTrue();
});
