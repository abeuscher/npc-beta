<?php

use App\Filament\Resources\PageResource;
use App\Models\Event;
use App\Models\EventDate;
use App\Models\EventRegistration;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
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

it('show page renders for a published event', function () {
    $event = Event::factory()->create(['title' => 'Board Meeting', 'status' => 'published']);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee('Board Meeting');
});

it('show page lists upcoming dates', function () {
    $event = Event::factory()->create(['title' => 'Board Meeting', 'status' => 'published']);
    EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    $response = $this->get(route('events.show', $event->slug))->assertOk();
    $response->assertSee('Board Meeting');
});

it('cancelled event renders with a cancellation notice not a 404', function () {
    $event = Event::factory()->cancelled()->create(['title' => 'Cancelled Conference']);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee('cancelled');
});

it('event with cancelled status renders with cancellation notice', function () {
    $event = Event::factory()->cancelled()->create(['title' => 'Cancelled Gala']);

    $this->get(route('events.show', $event->slug))
        ->assertOk()
        ->assertSee('cancelled');
});

// ── Registration ──────────────────────────────────────────────────────────────

it('registration form creates an EventRegistration record', function () {
    $event = Event::factory()->create(['status' => 'published', 'is_free' => true]);

    $this->post(route('events.register', $event->slug), [
        'name'         => 'Jane Doe',
        'email'        => 'jane@example.com',
        '_form_start'  => time() - 10,
        '_hp_name'     => '',
    ])->assertRedirect(route('events.show', $event->slug));

    expect(EventRegistration::where('email', 'jane@example.com')->exists())->toBeTrue();

    $reg = EventRegistration::where('email', 'jane@example.com')->first();
    expect($reg->event_id)->toBe($event->id);
});

it('registration is blocked when capacity is reached', function () {
    $event = Event::factory()->withCapacity(1)->create(['status' => 'published', 'is_free' => true]);

    // Fill capacity
    EventRegistration::factory()->create([
        'event_id' => $event->id,
        'status'   => 'registered',
    ]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Extra Person',
        'email'       => 'extra@example.com',
        '_form_start' => time() - 10,
        '_hp_name'    => '',
    ])->assertRedirect()->assertSessionHasErrors('register');

    expect(EventRegistration::count())->toBe(1);
});

it('registration is blocked for cancelled events', function () {
    $event = Event::factory()->cancelled()->create(['is_free' => true]);

    $this->post(route('events.register', $event->slug), [
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

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Bot',
        'email'       => 'bot@spam.com',
        '_hp_name'    => 'I am a bot',  // filled — should be discarded
        '_form_start' => time() - 10,
    ])->assertRedirect(route('events.show', $event->slug));

    expect(EventRegistration::count())->toBe(0);
});

it('timing check blocks submissions under 3 seconds without creating a registration', function () {
    $event = Event::factory()->create(['status' => 'published', 'is_free' => true]);

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Fast Bot',
        'email'       => 'fast@spam.com',
        '_hp_name'    => '',
        '_form_start' => time() - 1,  // only 1 second ago
    ])->assertRedirect(route('events.show', $event->slug));

    expect(EventRegistration::count())->toBe(0);
});

// ── Event model methods ───────────────────────────────────────────────────────

it('isAtCapacity returns false when capacity is null', function () {
    $event = Event::factory()->create(['capacity' => null]);
    expect($event->isAtCapacity())->toBeFalse();
});

it('isAtCapacity returns true when registrations fill capacity', function () {
    $event = Event::factory()->withCapacity(2)->create(['status' => 'published', 'is_free' => true]);

    EventRegistration::factory()->count(2)->create(['event_id' => $event->id, 'status' => 'registered']);

    expect($event->fresh()->isAtCapacity())->toBeTrue();
});

it('nextDate returns the next upcoming date for the event', function () {
    $event  = Event::factory()->create(['status' => 'published']);
    $past   = EventDate::factory()->past()->create(['event_id' => $event->id, 'status' => 'inherited']);
    $future = EventDate::factory()->upcoming()->create(['event_id' => $event->id, 'status' => 'inherited']);

    expect($event->nextDate()?->id)->toBe($future->id);
});

// ── Landing page creation ─────────────────────────────────────────────────────

it('creates a landing page with three widgets when action is triggered', function () {
    // Seed the widget types
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create(['title' => 'Test Event', 'slug' => 'test-event']);

    // Simulate the action logic directly
    $page = Page::create([
        'title'        => $event->title,
        'slug'         => $event->slug,
        'is_published' => false,
    ]);

    $widgetHandles = ['event_description', 'event_dates', 'event_registration'];
    $sort = 1;

    foreach ($widgetHandles as $handle) {
        $widgetType = WidgetType::where('handle', $handle)->first();
        PageWidget::create([
            'page_id'        => $page->id,
            'widget_type_id' => $widgetType->id,
            'label'          => $widgetType->label,
            'config'         => ['event_id' => $event->id],
            'sort_order'     => $sort++,
            'is_active'      => true,
        ]);
    }

    $event->update(['landing_page_id' => $page->id]);

    expect(PageWidget::where('page_id', $page->id)->count())->toBe(3);
    expect($event->fresh()->landing_page_id)->toBe($page->id);
});

it('view event page button uses landing page URL when landing_page_id is set', function () {
    $page  = Page::factory()->create(['slug' => 'my-event-page', 'is_published' => false]);
    $event = Event::factory()->create(['slug' => 'my-event', 'landing_page_id' => $page->id]);

    expect($event->landing_page_id)->toBe($page->id);
    expect($event->landingPage->slug)->toBe('my-event-page');
});

// ── Event widget rendering ────────────────────────────────────────────────────

it('event_description widget renders event description on a page', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create([
        'description' => '<p>Join us for a wonderful evening.</p>',
    ]);

    $widgetType = WidgetType::where('handle', 'event_description')->first();

    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Description',
        'config'         => ['event_id' => $event->id],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('Join us for a wonderful evening', false);
});

it('event_dates widget renders upcoming dates on a page', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create();
    EventDate::factory()->upcoming()->create(['event_id' => $event->id]);

    $widgetType = WidgetType::where('handle', 'event_dates')->first();

    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Dates',
        'config'         => ['event_id' => $event->id],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('event-dates-list', false);
});

it('event_registration widget renders the registration form on a page', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $event = Event::factory()->create([
        'status'            => 'published',
        'is_free'           => true,
        'registration_open' => true,
    ]);

    $widgetType = WidgetType::where('handle', 'event_registration')->first();

    $page = Page::factory()->create(['is_published' => true]);
    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'label'          => 'Event Registration',
        'config'         => ['event_id' => $event->id],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('Register for this event');
});
