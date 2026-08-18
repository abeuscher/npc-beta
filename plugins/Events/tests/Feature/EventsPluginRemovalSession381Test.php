<?php

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use App\Plugins\ImporterRegistry;
use App\Services\Import\CsvTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\EventsRemovedTestCase;

uses(EventsRemovedTestCase::class, RefreshDatabase::class);

// Session 381 (Plugin Architecture arc P5): the remove-the-line mirror for the
// Events vertical. The application boots with the Events line stripped from
// config('plugins'); the whole events surface must vanish — routes, admin
// pages, importer, widgets, schedule, webhook fulfillment — while the DATA is
// kept and every other surface runs untouched.

it('does not load the Events provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Events\EventsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeFalse();
});

it('drops all three public event routes', function () {
    expect(Route::has('events.register'))->toBeFalse()
        ->and(Route::has('portal.events.register'))->toBeFalse()
        ->and(Route::has('api.events.json'))->toBeFalse();
});

it('answers 405 on the POST register paths — the page catch-all owns GET on every path', function () {
    $event = Event::factory()->create(['status' => 'published']);

    $this->post("/events/{$event->slug}/register", ['name' => 'X', 'email' => 'x@example.com'])
        ->assertStatus(405);
    $this->post("/account/events/{$event->slug}/register")
        ->assertStatus(405);
});

it('lets api/events.json fall through to the page catch-all as a 404', function () {
    $this->get('/api/events.json')->assertStatus(404);
});

it('drops the admin event resource and both import pages', function () {
    foreach ([
        'filament.admin.resources.events.index',
        'filament.admin.resources.events.create',
        'filament.admin.resources.events.edit',
        'filament.admin.resources.events.registrations',
        'filament.admin.pages.import-events-page',
        'filament.admin.pages.import-events-progress-page',
    ] as $name) {
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('schedules no reminders job and registers no reminders command', function () {
    $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'events:send-reminders'));

    expect($scheduled)->toHaveCount(0)
        ->and(collect(app(\Illuminate\Contracts\Console\Kernel::class)->all())->has('events:send-reminders'))->toBeFalse();
});

it('leaves both models unobserved — writes fire no plugin hooks', function () {
    expect(app('events')->hasListeners('eloquent.created: ' . Event::class))->toBeFalse()
        ->and(app('events')->hasListeners('eloquent.created: ' . EventRegistration::class))->toBeFalse();
});

it('withdraws the importer contribution from every core surface', function () {
    expect(app(ImporterRegistry::class)->find('events'))->toBeNull()
        ->and((new \App\Filament\Pages\ImporterPage())->getPluginImporters())->toBe([]);

    expect(fn () => CsvTemplateService::headersFor('events'))
        ->toThrow(InvalidArgumentException::class);
});

it('skips the demo events import source when seeding', function () {
    $this->artisan('seed:fake-import-sources')->assertSuccessful();

    expect(\App\Models\ImportSource::where('name', 'Demo Fake Data — Events')->exists())->toBeFalse();
});

it('drops the seven event widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    foreach ([
        'event_description', 'event_image', 'event_mini_calendar',
        'event_registration', 'events_listing', 'portal_event_registrations',
        'this_weeks_events',
    ] as $handle) {
        expect($handles)->not->toContain($handle);
    }
});

it('keeps event data queryable — vanish is never destructive', function () {
    $event = Event::factory()->create(['status' => 'published']);
    EventRegistration::factory()->create(['event_id' => $event->id, 'email' => 'kept@example.com']);

    expect(Event::count())->toBe(1)
        ->and(EventRegistration::where('email', 'kept@example.com')->exists())->toBeTrue();
});

it('renders an event landing page with its event widgets skipped, not fatal', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $event = Event::factory()->create(['status' => 'published']);
    \App\Services\EventLandingPageFactory::createForEvent($event);

    $page = $event->fresh()->landingPage;
    expect($page)->not->toBeNull();

    $this->get('/' . $page->slug)->assertOk();
});

it('leaves a late events webhook dispatch with no listener: rows stay pending, data kept', function () {
    $event = Event::factory()->create(['status' => 'published']);
    $tier  = TicketTier::factory()->for($event)->create(['price' => 25]);

    $row = EventRegistration::factory()->create([
        'event_id'          => $event->id,
        'ticket_tier_id'    => $tier->id,
        'email'             => 'late@example.com',
        'status'            => 'pending',
        'stripe_session_id' => 'cs_late_1',
        'quantity'          => 1,
    ]);

    event(new CheckoutSettled((object) [
        'id'               => 'cs_late_1',
        'payment_intent'   => 'pi_late_1',
        'amount_total'     => 2500,
        'customer_details' => (object) ['email' => 'late@example.com'],
    ], (object) ['event_registration_checkout' => '1']));

    expect($row->fresh()->status)->toBe('pending')
        ->and(Transaction::where('stripe_id', 'pi_late_1')->exists())->toBeFalse();
});

// ── The other surfaces run untouched ─────────────────────────────────────────

it('leaves the Payments plugin loaded and its webhook route registered', function () {
    expect(app()->providerIsLoaded(\Plugins\Payments\PaymentsServiceProvider::class))->toBeTrue()
        ->and(Route::has('webhooks.stripe'))->toBeTrue();
});

it('streams the core CSV templates unaffected', function () {
    expect(CsvTemplateService::headersFor('contacts'))->not->toBeEmpty()
        ->and(CsvTemplateService::headersFor('donations'))->not->toBeEmpty();
});
