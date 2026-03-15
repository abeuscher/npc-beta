<?php

use App\Models\Event;
use App\Models\EventDate;
use App\Models\EventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['site.events_prefix' => 'events']);
});

// ── Scopes ────────────────────────────────────────────────────────────────────

it('published scope returns only published events', function () {
    Event::factory()->create(['status' => 'published', 'title' => 'Live Event']);
    Event::factory()->draft()->create(['title' => 'Draft Event']);
    Event::factory()->cancelled()->create(['title' => 'Cancelled Event']);

    $published = Event::published()->pluck('title');

    expect($published)->toContain('Live Event')
        ->not->toContain('Draft Event')
        ->not->toContain('Cancelled Event');
});

it('upcoming scope returns only events with future dates', function () {
    $future = Event::factory()->create();
    EventDate::factory()->create(['event_id' => $future->id, 'starts_at' => now()->addDays(5)]);

    $past = Event::factory()->create();
    EventDate::factory()->create(['event_id' => $past->id, 'starts_at' => now()->subDays(5)]);

    $upcoming = Event::upcoming()->pluck('id');

    expect($upcoming)->toContain($future->id)
        ->not->toContain($past->id);
});

// ── Public event index ────────────────────────────────────────────────────────

it('published upcoming event dates appear on the events index page', function () {
    $event = Event::factory()->create(['title' => 'Annual Gala', 'status' => 'published']);
    EventDate::factory()->upcoming()->create(['event_id' => $event->id, 'status' => 'inherited']);

    $this->get('/events')->assertOk()->assertSee('Annual Gala');
});

it('draft events do not appear on the events index page', function () {
    $event = Event::factory()->draft()->create(['title' => 'Hidden Draft']);
    EventDate::factory()->upcoming()->create(['event_id' => $event->id, 'status' => 'inherited']);

    $this->get('/events')->assertOk()->assertDontSee('Hidden Draft');
});

it('past event dates do not appear on the events index page', function () {
    $event = Event::factory()->create(['title' => 'Past Event', 'status' => 'published']);
    EventDate::factory()->past()->create(['event_id' => $event->id, 'status' => 'inherited']);

    $this->get('/events')->assertOk()->assertDontSee('Past Event');
});

// ── Public event show page ────────────────────────────────────────────────────

it('show page renders for a published event date', function () {
    $event = Event::factory()->create(['title' => 'Board Meeting', 'status' => 'published']);
    $date  = EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    $this->get(route('events.show', [$event->slug, $date->id]))
        ->assertOk()
        ->assertSee('Board Meeting');
});

it('cancelled event date renders with a cancellation notice not a 404', function () {
    $event = Event::factory()->create(['status' => 'published', 'title' => 'Cancelled Gala']);
    $date  = EventDate::factory()->upcoming()->cancelled()->create(['event_id' => $event->id]);

    $this->get(route('events.show', [$event->slug, $date->id]))
        ->assertOk()
        ->assertSee('cancelled');
});

it('event with cancelled status renders with cancellation notice', function () {
    $event = Event::factory()->cancelled()->create(['title' => 'Cancelled Conference']);
    $date  = EventDate::factory()->upcoming()->create(['event_id' => $event->id, 'status' => 'inherited']);

    $this->get(route('events.show', [$event->slug, $date->id]))
        ->assertOk()
        ->assertSee('cancelled');
});

// ── Registration ──────────────────────────────────────────────────────────────

it('registration form creates an EventRegistration record', function () {
    $event = Event::factory()->create(['status' => 'published', 'is_free' => true]);
    $date  = EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    $this->post(route('events.register', [$event->slug, $date->id]), [
        'name'         => 'Jane Doe',
        'email'        => 'jane@example.com',
        '_form_start'  => time() - 10,
        '_hp_name'     => '',
    ])->assertRedirect(route('events.show', [$event->slug, $date->id]));

    expect(EventRegistration::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('registration is blocked when capacity is reached', function () {
    $event = Event::factory()->withCapacity(1)->create(['status' => 'published', 'is_free' => true]);
    $date  = EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    // Fill capacity
    EventRegistration::factory()->create([
        'event_date_id' => $date->id,
        'status'        => 'registered',
    ]);

    $this->post(route('events.register', [$event->slug, $date->id]), [
        'name'        => 'Extra Person',
        'email'       => 'extra@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionHasErrors('register');

    expect(EventRegistration::count())->toBe(1);
});

it('registration is blocked for cancelled event dates', function () {
    $event = Event::factory()->create(['status' => 'published', 'is_free' => true]);
    $date  = EventDate::factory()->upcoming()->cancelled()->create(['event_id' => $event->id]);

    $this->post(route('events.register', [$event->slug, $date->id]), [
        'name'        => 'Bot User',
        'email'       => 'bot@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionHasErrors('register');

    expect(EventRegistration::count())->toBe(0);
});

// ── Honeypot & timing ─────────────────────────────────────────────────────────

it('honeypot field triggers silent success without creating a registration', function () {
    $event = Event::factory()->create(['status' => 'published', 'is_free' => true]);
    $date  = EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    $this->post(route('events.register', [$event->slug, $date->id]), [
        'name'        => 'Bot',
        'email'       => 'bot@spam.com',
        '_hp_name'    => 'I am a bot',  // filled — should be discarded
        '_form_start' => time() - 10,
    ])->assertRedirect(route('events.show', [$event->slug, $date->id]));

    expect(EventRegistration::count())->toBe(0);
});

it('timing check blocks submissions under 3 seconds without creating a registration', function () {
    $event = Event::factory()->create(['status' => 'published', 'is_free' => true]);
    $date  = EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    $this->post(route('events.register', [$event->slug, $date->id]), [
        'name'        => 'Fast Bot',
        'email'       => 'fast@spam.com',
        '_hp_name'    => '',
        '_form_start' => time() - 1,  // only 1 second ago
    ])->assertRedirect(route('events.show', [$event->slug, $date->id]));

    expect(EventRegistration::count())->toBe(0);
});

// ── Event model methods ───────────────────────────────────────────────────────

it('isAtCapacity returns false when capacity is null', function () {
    $event = Event::factory()->create(['capacity' => null]);
    expect($event->isAtCapacity())->toBeFalse();
});

it('isAtCapacity returns true when registrations fill capacity', function () {
    $event = Event::factory()->withCapacity(2)->create(['status' => 'published', 'is_free' => true]);
    $date  = EventDate::factory()->create(['event_id' => $event->id]);

    EventRegistration::factory()->count(2)->create(['event_date_id' => $date->id, 'status' => 'registered']);

    expect($event->fresh()->isAtCapacity())->toBeTrue();
});

it('nextDate returns the next upcoming date for the event', function () {
    $event  = Event::factory()->create(['status' => 'published']);
    $past   = EventDate::factory()->past()->create(['event_id' => $event->id, 'status' => 'inherited']);
    $future = EventDate::factory()->upcoming()->create(['event_id' => $event->id, 'status' => 'inherited']);

    expect($event->nextDate()?->id)->toBe($future->id);
});
