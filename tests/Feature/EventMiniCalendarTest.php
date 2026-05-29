<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Widgets\EventMiniCalendar\EventMiniCalendarDefinition;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function eventMiniCalendarWidgetData(array $config = []): array
{
    $contract = (new EventMiniCalendarDefinition())->dataContract($config);
    $slot = new SlotContext(new PageAmbientContext());

    return app(ContractResolver::class)->resolve([$contract], $slot)[0];
}

function renderEventMiniCalendar(array $config = ['heading' => '']): string
{
    $page = Page::factory()->create(['status' => 'published']);
    $pw = $page->widgets()->create([
        'widget_type_id' => WidgetType::where('handle', 'event_mini_calendar')->firstOrFail()->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    return WidgetRenderer::render($pw->fresh('widgetType'))['html'] ?? '';
}

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);
});

// ── Data contract — the ±1-month window ──────────────────────────────────────

it('pulls published events within the previous, current, and next month and excludes the rest', function () {
    $monthStart = Carbon::now()->startOfMonth();

    $inPrev    = Event::factory()->create(['title' => 'In Prev',    'starts_at' => $monthStart->copy()->subMonthNoOverflow()->setDay(10)->setTime(18, 0)]);
    $inCurrent = Event::factory()->create(['title' => 'In Current', 'starts_at' => $monthStart->copy()->setDay(15)->setTime(18, 0)]);
    $inNext    = Event::factory()->create(['title' => 'In Next',    'starts_at' => $monthStart->copy()->addMonthNoOverflow()->setDay(5)->setTime(18, 0)]);

    // Outside the window — two months out — and a draft inside the window.
    Event::factory()->create(['title' => 'Too Far',  'starts_at' => $monthStart->copy()->addMonthsNoOverflow(3)->setDay(5)]);
    Event::factory()->draft()->create(['title' => 'Draft', 'starts_at' => $monthStart->copy()->setDay(20)]);

    $titles = collect(eventMiniCalendarWidgetData()['items'])->pluck('title');

    expect($titles)->toContain('In Prev', 'In Current', 'In Next')
        ->not->toContain('Too Far')
        ->not->toContain('Draft');
});

// ── Template render ──────────────────────────────────────────────────────────

it('renders three month panels with only the current month visible', function () {
    $html = renderEventMiniCalendar();

    expect(substr_count($html, 'class="emc-month"'))->toBe(3)
        // panels 0 and 2 carry the hidden attribute; the current panel (1) does not.
        ->and($html)->toMatch('/data-month-index="0"\s+hidden/')
        ->and($html)->toMatch('/data-month-index="2"\s+hidden/')
        ->and($html)->not->toMatch('/data-month-index="1"\s+hidden/');
});

it('declares the events-index.mini-calendar tour anchor', function () {
    expect(renderEventMiniCalendar())->toContain('data-tour="events-index.mini-calendar"');
});

it('marks days with events as clickable density cells carrying a data-day key', function () {
    $day = Carbon::now()->startOfMonth()->setDay(12);
    Event::factory()->create(['starts_at' => $day->copy()->setTime(19, 0)]);

    $html = renderEventMiniCalendar();

    expect($html)->toContain('data-day="' . $day->format('Y-m-d') . '"')
        ->toContain('emc-day--has-events');
});

it('escalates the density tier with the number of events on a day', function () {
    $monthStart = Carbon::now()->startOfMonth();
    $oneDay   = $monthStart->copy()->setDay(8);
    $threeDay = $monthStart->copy()->setDay(18);

    Event::factory()->create(['starts_at' => $oneDay->copy()->setTime(10, 0)]);
    Event::factory()->count(3)->create(['starts_at' => $threeDay->copy()->setTime(10, 0)]);

    $html = renderEventMiniCalendar();

    expect($html)
        ->toMatch('/emc-day--low[^>]*data-day="' . preg_quote($oneDay->format('Y-m-d'), '/') . '"/')
        ->toMatch('/emc-day--high[^>]*data-day="' . preg_quote($threeDay->format('Y-m-d'), '/') . '"/');
});

it('renders an empty month grid with no event days when there are no events', function () {
    $html = renderEventMiniCalendar();

    expect($html)->toContain(Carbon::now()->format('F Y')) // current month label
        ->toContain('emc-month__weekdays')
        ->not->toContain('emc-day--has-events');
});

it('renders the optional heading only when set', function () {
    expect(renderEventMiniCalendar(['heading' => 'This Month']))->toContain('This Month')
        ->and(renderEventMiniCalendar(['heading' => '']))->not->toContain('widget-event-mini-calendar__heading');
});

// ── Self-contained events list ───────────────────────────────────────────────

it('renders each event as an expandable toggle with inline detail', function () {
    $day = Carbon::now()->startOfMonth()->setDay(14);
    Event::factory()->create([
        'title' => 'Gala Night', 'slug' => 'gala-night',
        'starts_at' => $day->copy()->setTime(19, 0),
        'address_line_1' => '123 Moody St', 'city' => 'Waltham', 'state' => 'MA',
    ]);

    $html = renderEventMiniCalendar(['list_mode' => 'day']);

    expect($html)->toContain('data-day-events="' . $day->format('Y-m-d') . '"')
        ->toContain('emc-event__toggle')
        ->toContain('aria-expanded="false"')
        ->toContain('Gala Night')
        ->toContain('emc-event__detail')
        ->toContain('123 Moody St'); // a fact from the inline detail
});

it('shows the Event page link only when the event has a landing page (no dead end otherwise)', function () {
    $day = Carbon::now()->startOfMonth()->setDay(12);

    $withLp = Event::factory()->create(['title' => 'Has LP', 'slug' => 'has-lp', 'starts_at' => $day->copy()->setTime(10, 0)]);
    $lp = Page::factory()->create(['type' => 'default', 'status' => 'published', 'slug' => 'events/has-lp']);
    $withLp->update(['landing_page_id' => $lp->id]);

    Event::factory()->create(['title' => 'No LP', 'slug' => 'no-lp', 'starts_at' => $day->copy()->setTime(14, 0)]);

    $html = renderEventMiniCalendar(['list_mode' => 'day']);

    // The LP event carries an outbound link to its page…
    expect($html)->toMatch('#<a class="emc-event__link" href="[^"]*events/has-lp"#')
        // …and exactly one Event-page link exists (the no-LP event has none).
        ->and(substr_count($html, 'emc-event__link'))->toBe(1)
        ->and($html)->toContain('No LP'); // still listed, just without a link
});

it('caps the event list height only when events_max_height is set', function () {
    Event::factory()->create(['starts_at' => Carbon::now()->startOfMonth()->setDay(10)->setTime(18, 0)]);

    expect(renderEventMiniCalendar(['list_mode' => 'day', 'events_max_height' => '320']))
        ->toMatch('/emc-events--day"\s+style="max-height:320px;overflow-y:auto;"/');

    expect(renderEventMiniCalendar(['list_mode' => 'day', 'events_max_height' => '']))
        ->not->toContain('max-height:');
});

it('gates the inline description behind the show_description toggle', function () {
    $day = Carbon::now()->startOfMonth()->setDay(9);
    Event::factory()->create([
        'title' => 'Talk', 'slug' => 'talk', 'starts_at' => $day->copy()->setTime(12, 0),
        'description' => '<p>Bring your own lunch and questions.</p>',
    ]);

    expect(renderEventMiniCalendar(['list_mode' => 'day', 'show_description' => true]))
        ->toContain('emc-event__desc')
        ->toContain('Bring your own lunch');

    expect(renderEventMiniCalendar(['list_mode' => 'day', 'show_description' => false]))
        ->not->toContain('emc-event__desc')
        ->not->toContain('Bring your own lunch');
});

it('month mode lists the visible month and makes day cells non-interactive', function () {
    $monthStart = Carbon::now()->startOfMonth();
    Event::factory()->create(['title' => 'Mid-Month Mixer', 'starts_at' => $monthStart->copy()->setDay(10)->setTime(18, 0)]);

    $html = renderEventMiniCalendar(['list_mode' => 'month']);

    // The current month's list is the visible one (index 1, not hidden).
    expect($html)->toContain('emc-events--month')
        ->toContain('Mid-Month Mixer')
        ->toMatch('/data-month-events="1"\s*>/')          // current month list not hidden
        ->toMatch('/data-month-events="0"\s+hidden/')     // adjacent months hidden
        // In month mode day cells are inert spans — no day is selectable
        // (the month-nav arrows are still buttons, but they carry no data-day).
        ->not->toContain('data-day=');
});

it('month mode shows an empty line for a month with no events', function () {
    // No events seeded → the visible (current) month renders the empty line.
    $html = renderEventMiniCalendar(['list_mode' => 'month']);

    expect($html)->toContain('emc-events--month')
        ->toContain('No events this month.');
});
