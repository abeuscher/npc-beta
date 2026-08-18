<?php

use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use App\Plugins\ImporterRegistry;
use App\Services\Import\CsvTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Plugins\Events\Mail\RegistrationConfirmation;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 381 (Plugin Architecture arc P5): the enabled twin for the Events
// vertical plugin. The shipped config/plugins.php carries the plugin, so the
// plain TestCase boots it; EventsPluginRemovalSession381Test is the
// remove-the-line mirror.

it('ships the Events provider in the plugin list and loads it', function () {
    expect(config('plugins'))->toContain(\Plugins\Events\EventsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Events\EventsServiceProvider::class))->toBeTrue();
});

it('registers the three public event routes under their established names', function () {
    expect(Route::has('events.register'))->toBeTrue()
        ->and(Route::has('portal.events.register'))->toBeTrue()
        ->and(Route::has('api.events.json'))->toBeTrue();
});

it('serves the events JSON endpoint ahead of the page catch-all', function () {
    $this->getJson('/api/events.json')->assertOk()->assertJson([]);
});

it('registers both observers from the provider', function () {
    expect(app('events')->hasListeners('eloquent.created: ' . Event::class))->toBeTrue()
        ->and(app('events')->hasListeners('eloquent.created: ' . EventRegistration::class))->toBeTrue();
});

it('registers the event policy via the gate', function () {
    expect(Gate::getPolicyFor(Event::class))
        ->toBeInstanceOf(\Plugins\Events\Policies\EventPolicy::class);
});

it('registers the reminders command and its daily schedule', function () {
    expect(collect(app(\Illuminate\Contracts\Console\Kernel::class)->all())->has('events:send-reminders'))->toBeTrue();

    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'events:send-reminders'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 8 * * *');
});

it('registers the seven event widgets in the registry with their established handles', function () {
    $handles = collect(app(\App\Services\WidgetRegistry::class)->all())
        ->map(fn ($def) => $def->handle());

    foreach ([
        'event_description', 'event_image', 'event_mini_calendar',
        'event_registration', 'events_listing', 'portal_event_registrations',
        'this_weeks_events',
    ] as $handle) {
        expect($handles)->toContain($handle);
    }
});

it('discovers the admin event resource from the plugin at its established routes', function () {
    foreach ([
        'filament.admin.resources.events.index',
        'filament.admin.resources.events.create',
        'filament.admin.resources.events.edit',
        'filament.admin.resources.events.registrations',
        'filament.admin.pages.import-events-page',
        'filament.admin.pages.import-events-progress-page',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("missing route {$name}");
    }
});

// ── The importer socket (contract surface 8) ─────────────────────────────────

it('contributes the events importer through the registry', function () {
    $contribution = app(ImporterRegistry::class)->find('events');

    expect($contribution)->not->toBeNull()
        ->and($contribution->modelType)->toBe('event')
        ->and($contribution->pageClass)->toBe(\Plugins\Events\Filament\Pages\ImportEventsPage::class)
        ->and($contribution->progressPageClass)->toBe(\Plugins\Events\Filament\Pages\ImportEventsProgressPage::class);
});

it('streams the events CSV template through the socket with its established headers', function () {
    $headers = CsvTemplateService::headersFor('events');

    expect($headers)->toContain('Event Title')
        ->and($headers)->toContain('Registration Ticket Type')
        ->and($headers)->toContain('Contact Email');
});

it('lists the events importer on the hub page', function () {
    $importers = (new \App\Filament\Pages\ImporterPage())->getPluginImporters();

    expect(collect($importers)->pluck('slug'))->toContain('events');
});

it('seeds the demo events import source through the socket', function () {
    $this->artisan('seed:fake-import-sources')->assertSuccessful();

    expect(\App\Models\ImportSource::where('name', 'Demo Fake Data — Events')->exists())->toBeTrue();
});

// ── The inbound inversion (contract surface 10, events slice) ────────────────

it('wires the CheckoutSettled listener: dispatch promotes the pending order, records one transaction, queues one confirmation', function () {
    Mail::fake();

    $event   = Event::factory()->create(['status' => 'published']);
    $tier    = TicketTier::factory()->for($event)->create(['price' => 25]);
    $sid     = 'cs_test_settle_1';

    $r1 = EventRegistration::factory()->create([
        'event_id'          => $event->id,
        'ticket_tier_id'    => $tier->id,
        'email'             => 'settle@example.com',
        'status'            => 'pending',
        'stripe_session_id' => $sid,
        'quantity'          => 2,
    ]);

    event(new CheckoutSettled((object) [
        'id'               => $sid,
        'payment_intent'   => 'pi_settle_1',
        'amount_total'     => 5000,
        'customer_details' => (object) ['email' => 'settle@example.com', 'name' => 'Settle Person'],
    ], (object) ['event_registration_checkout' => '1']));

    expect($r1->fresh()->status)->toBe('registered')
        ->and($r1->fresh()->stripe_payment_intent_id)->toBe('pi_settle_1');

    expect(Transaction::where('stripe_id', 'pi_settle_1')->count())->toBe(1)
        ->and(Contact::where('email', 'settle@example.com')->exists())->toBeTrue();

    Mail::assertQueued(RegistrationConfirmation::class, 1);
});

it('replays idempotently: a second dispatch finds nothing pending and writes nothing', function () {
    Mail::fake();

    $event = Event::factory()->create(['status' => 'published']);
    $tier  = TicketTier::factory()->for($event)->create(['price' => 25]);
    $sid   = 'cs_test_settle_2';

    EventRegistration::factory()->create([
        'event_id'          => $event->id,
        'ticket_tier_id'    => $tier->id,
        'email'             => 'replay@example.com',
        'status'            => 'pending',
        'stripe_session_id' => $sid,
        'quantity'          => 1,
    ]);

    $payload = (object) [
        'id'               => $sid,
        'payment_intent'   => 'pi_settle_2',
        'amount_total'     => 2500,
        'customer_details' => (object) ['email' => 'replay@example.com', 'name' => 'Replay Person'],
    ];
    $metadata = (object) ['event_registration_checkout' => '1'];

    event(new CheckoutSettled($payload, $metadata));
    event(new CheckoutSettled($payload, $metadata));

    expect(Transaction::where('stripe_id', 'pi_settle_2')->count())->toBe(1);
    Mail::assertQueued(RegistrationConfirmation::class, 1);
});

it('dispatches CheckoutSettled from the webhook events branch', function () {
    \Illuminate\Support\Facades\Event::fake([CheckoutSettled::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleCheckoutSessionCompleted');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'             => 'cs_test_dispatch',
        'payment_intent' => 'pi_dispatch',
        'amount_total'   => 1000,
        'metadata'       => ['event_registration_checkout' => '1'],
    ]]]);

    $response = $reflection->invoke($controller, $stripeEvent);

    expect($response->getStatusCode())->toBe(200);
    \Illuminate\Support\Facades\Event::assertDispatched(CheckoutSettled::class);
});
