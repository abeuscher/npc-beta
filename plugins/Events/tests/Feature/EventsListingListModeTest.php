<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\Tag;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function renderEventsListing(array $config = []): string
{
    $host = Page::factory()->create(['status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'events_listing')->firstOrFail()->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    return WidgetRenderer::render($pw->fresh('widgetType'))['html'] ?? '';
}

function eventWithLandingPage(array $attrs): Event
{
    $lp = Page::factory()->create(['type' => 'default', 'status' => 'published', 'slug' => 'events/' . ($attrs['slug'] ?? 'event')]);

    return Event::factory()->create([...$attrs, 'landing_page_id' => $lp->id]);
}

// ── Layout is additive — carousel stays the default ──────────────────────────

it('renders the grid/carousel layout by default (no layout toggles)', function () {
    Event::factory()->create(['title' => 'Grid Event', 'starts_at' => now()->addDays(2)]);

    $html = renderEventsListing();

    expect($html)->toContain('widget-events-listing__swiper')
        ->toContain('x-ref="listingData"')
        ->not->toContain('events-list__rows')
        ->not->toContain('widget-events-listing--list');
});

// ── The two layout toggles are independent ───────────────────────────────────

it('renders side-by-side rows when the row layout toggle is on, without day headings', function () {
    eventWithLandingPage(['title' => 'Row Event', 'slug' => 'row-event', 'starts_at' => now()->addDays(2)->setTime(18, 0)]);

    $html = renderEventsListing(['side_by_side_rows' => true]);

    expect($html)->toContain('widget-events-listing--list')
        ->toContain('events-list__rows')
        ->toContain('data-event-row')
        ->toContain('Row Event')
        ->not->toContain('widget-events-listing__swiper')
        ->not->toContain('data-day-group'); // no grouping
});

it('groups events under day headings when the day toggle is on, independent of row layout', function () {
    $day = now()->addDays(3)->startOfDay();
    eventWithLandingPage(['title' => 'Morning Talk', 'slug' => 'morning-talk', 'starts_at' => $day->copy()->setTime(9, 0)]);
    eventWithLandingPage(['title' => 'Evening Gala', 'slug' => 'evening-gala', 'starts_at' => $day->copy()->setTime(19, 0)]);

    // Day grouping with the default card layout (not side-by-side).
    $cards = renderEventsListing(['group_by_day' => true]);
    expect($cards)->toContain('data-day-group')
        ->toContain('events-list__day-heading')
        ->toContain('content-card') // cards, not rows
        ->toContain(Carbon::parse($day)->format('l, F j'));

    // Day grouping with side-by-side rows = the full "luma" shape.
    $rows = renderEventsListing(['group_by_day' => true, 'side_by_side_rows' => true]);
    expect(substr_count($rows, 'data-day-group'))->toBe(1) // both events share one day
        ->and(substr_count($rows, 'data-event-row'))->toBe(2)
        ->and($rows)->toContain('events-list__rows');
});

it('lays side-by-side rows into the configured number of columns', function () {
    eventWithLandingPage(['title' => 'Col Event', 'slug' => 'col-event', 'starts_at' => now()->addDays(2)]);

    expect(renderEventsListing(['side_by_side_rows' => true, 'columns' => '3']))
        ->toMatch('#class="events-list__rows" style="grid-template-columns: repeat\(3, 1fr\);"#');

    expect(renderEventsListing(['side_by_side_rows' => true]))
        ->toContain('grid-template-columns: repeat(3, 1fr);'); // default columns
});

it('links rows to the landing page', function () {
    eventWithLandingPage(['title' => 'Linked', 'slug' => 'linked', 'starts_at' => now()->addDays(2)]);

    expect(renderEventsListing(['side_by_side_rows' => true]))
        ->toMatch('#href="[^"]*events/linked"#');
});

// ── Styleable day heading via tokens ─────────────────────────────────────────

it('renders the day heading from the token template and replaces date tokens', function () {
    $day = now()->addDays(2)->startOfDay()->setTime(10, 0);
    eventWithLandingPage(['title' => 'Token Event', 'slug' => 'token-event', 'starts_at' => $day]);

    $html = renderEventsListing([
        'group_by_day'         => true,
        'day_heading_template' => '<h4 class="custom-day">{{day.weekday}} — {{day.month}} {{day.number}}</h4>',
    ]);

    expect($html)->toContain('<h4 class="custom-day">' . $day->format('l') . ' — ' . $day->format('F') . ' ' . $day->format('j') . '</h4>');
});

// ── Featured hero — present in all layouts ───────────────────────────────────

it('renders the featured hero in the carousel layout and removes it from the carousel items', function () {
    eventWithLandingPage(['title' => 'Headliner', 'slug' => 'headliner', 'starts_at' => now()->addDays(2)]);
    eventWithLandingPage(['title' => 'Sidekick', 'slug' => 'sidekick', 'starts_at' => now()->addDays(4)]);

    $html = renderEventsListing(['featured_event_slug' => 'headliner']);

    expect($html)->toContain('data-tour="events-index.featured"')
        ->toContain('events-featured__title')
        ->toContain('Headliner')
        ->toContain('widget-events-listing__swiper'); // still the carousel

    // The featured event is excluded from the carousel data so it isn't duplicated.
    preg_match('#<script x-ref="listingData" type="application/json">(.+?)</script>#s', $html, $match);
    $items = json_decode($match[1], true)['items'];
    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Sidekick');
});

it('renders the featured hero in the row layout and removes it from the day list', function () {
    eventWithLandingPage(['title' => 'Headliner', 'slug' => 'headliner', 'starts_at' => now()->addDays(2)]);
    eventWithLandingPage(['title' => 'Sidekick', 'slug' => 'sidekick', 'starts_at' => now()->addDays(4)]);

    $html = renderEventsListing(['side_by_side_rows' => true, 'featured_event_slug' => 'headliner']);

    expect($html)->toContain('data-tour="events-index.featured"')
        ->toContain('Headliner')
        ->and(substr_count($html, 'data-event-row'))->toBe(1)
        ->and($html)->toContain('Sidekick');
});

it('renders no featured hero when the slug is blank or unmatched', function () {
    eventWithLandingPage(['title' => 'Only Event', 'slug' => 'only-event', 'starts_at' => now()->addDays(2)]);

    expect(renderEventsListing(['featured_event_slug' => '']))
        ->not->toContain('data-tour="events-index.featured"');

    expect(renderEventsListing(['featured_event_slug' => 'does-not-exist']))
        ->not->toContain('data-tour="events-index.featured"');
});

// ── Event-type filter — a dropdown beside search, toggle-gated, all layouts ──

it('renders the event-type filter dropdown in the controls row when toggled on and tags exist', function () {
    $event = eventWithLandingPage(['title' => 'Tagged Event', 'slug' => 'tagged-event', 'starts_at' => now()->addDays(2)]);
    $tag = Tag::create(['name' => 'Fundraiser', 'type' => 'event']);
    $event->tags()->attach($tag->id);

    // Carousel layout — filter binds to Alpine state.
    $carousel = renderEventsListing(['show_event_type_filter' => true]);
    expect($carousel)->toContain('widget-events-listing__controls')
        ->toContain('widget-events-listing__filter')
        ->toContain('data-tour="events-index.filters"')
        ->toContain('x-model="typeFilter"')
        ->toContain('<option value="' . $tag->slug . '">Fundraiser</option>');

    // Row layout — filter is wired by the vanilla handler.
    $rows = renderEventsListing(['show_event_type_filter' => true, 'side_by_side_rows' => true]);
    expect($rows)->toContain('widget-events-listing__filter')
        ->toContain('data-type-filter')
        ->toMatch('#data-event-row data-tags="' . preg_quote($tag->slug, '#') . '"#');
});

it('hides the filter when the toggle is off or no events are tagged', function () {
    $event = eventWithLandingPage(['title' => 'Tagged', 'slug' => 'tagged', 'starts_at' => now()->addDays(2)]);
    $event->tags()->attach(Tag::create(['name' => 'Gala', 'type' => 'event'])->id);

    // Toggle off → no filter even with tags.
    expect(renderEventsListing(['show_event_type_filter' => false]))
        ->not->toContain('widget-events-listing__filter');

    // Toggle on but nothing tagged → no filter.
    expect(renderEventsListing(['show_event_type_filter' => true]))
        ->toContain('widget-events-listing__filter'); // (the tagged event above makes a tag exist)

    Event::query()->delete();
    eventWithLandingPage(['title' => 'Untagged', 'slug' => 'untagged-only', 'starts_at' => now()->addDays(2)]);
    expect(renderEventsListing(['show_event_type_filter' => true]))
        ->not->toContain('widget-events-listing__filter');
});

// ── Status badges (row layout) ───────────────────────────────────────────────

it('renders Free and Sold Out badges on rows', function () {
    eventWithLandingPage(['title' => 'Free Event', 'slug' => 'free-event', 'starts_at' => now()->addDays(2)]);
    eventWithLandingPage(['title' => 'Gone Event', 'slug' => 'gone-event', 'starts_at' => now()->addDays(3), 'sold_out' => true]);

    $html = renderEventsListing(['side_by_side_rows' => true]);

    expect($html)->toContain('events-list__badge--free')
        ->toContain('Free')
        ->toContain('events-list__badge--soldout')
        ->toContain('Sold Out');
});

// ── Art fallback ─────────────────────────────────────────────────────────────

it('renders a solid-colour date placeholder when a row event has no image', function () {
    $day = now()->addDays(2)->setTime(10, 0);
    eventWithLandingPage(['title' => 'Imageless', 'slug' => 'imageless', 'starts_at' => $day]);

    expect(renderEventsListing(['side_by_side_rows' => true]))
        ->toContain('events-list__placeholder')
        ->toContain(Carbon::parse($day)->format('M j'));
});

// ── ItemList / Event schema ──────────────────────────────────────────────────

it('emits an ItemList JSON-LD block with Event entries', function () {
    eventWithLandingPage(['title' => 'Schema Event', 'slug' => 'schema-event', 'starts_at' => now()->addDays(2)]);

    $html = renderEventsListing(['side_by_side_rows' => true]);

    expect($html)->toContain('application/ld+json')
        ->toContain('"@type":"ItemList"')
        ->toContain('"@type":"Event"')
        ->toContain('Schema Event');
});

// ── Empty state ──────────────────────────────────────────────────────────────

it('renders an empty state in the list layout when there are no upcoming events', function () {
    expect(renderEventsListing(['side_by_side_rows' => true]))
        ->toContain('events-list__empty')
        ->toContain('No upcoming events');
});

// ── Projection: sold_out + tags reach the row DTO ────────────────────────────

it('projects sold_out and tags onto the event row', function () {
    $event = Event::factory()->create(['title' => 'Projected', 'slug' => 'projected', 'starts_at' => now()->addDays(2), 'sold_out' => true]);
    $event->tags()->attach(Tag::create(['name' => 'Workshop', 'type' => 'event'])->id);

    // Carousel serialises every projected field into listingData — assert on it.
    $html = renderEventsListing();
    preg_match('#<script x-ref="listingData" type="application/json">(.+?)</script>#s', $html, $match);
    $items = json_decode($match[1], true)['items'];

    expect($items[0]['sold_out'])->toBeTrue()
        ->and($items[0]['tags'])->toBeArray()
        ->and($items[0]['tags'][0]['name'])->toBe('Workshop');
});
