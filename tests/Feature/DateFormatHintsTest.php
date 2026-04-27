<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\Support\DateFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00'));
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders EventsListing with the chosen event date format', function () {
    $startsAt = Carbon::parse('2026-09-12 17:30:00');

    Event::factory()->create([
        'title'     => 'Format Hints Event',
        'slug'      => 'format-hints-event',
        'status'    => 'published',
        'starts_at' => $startsAt,
        'ends_at'   => $startsAt->copy()->addHours(2),
    ]);

    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'eh-host', 'status' => 'published']);

    $tile = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="d">{{item.event_date}}</p>',
            'date_format'      => DateFormat::EVENT_TILE_DATE,
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $long = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="d">{{item.event_date}}</p>',
            'date_format'      => DateFormat::LONG_DATE,
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'sort_order' => 1,
        'is_active'  => true,
    ]);

    expect(WidgetRenderer::render($tile)['html'])->toContain('Sat, Sep 12')
        ->and(WidgetRenderer::render($long)['html'])->toContain('September 12, 2026');
});

it('falls back to the EventsListing default when date_format is unknown', function () {
    $startsAt = Carbon::parse('2026-09-12 17:30:00');

    Event::factory()->create([
        'title'     => 'Bad Hint Event',
        'slug'      => 'bad-hint-event',
        'status'    => 'published',
        'starts_at' => $startsAt,
    ]);

    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'eh-fallback', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="d">{{item.event_date}}</p>',
            'date_format'      => 'NOT_A_REAL_FORMAT',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    expect(WidgetRenderer::render($pw)['html'])->toContain('Sat, Sep 12');
});

it('renders ThisWeeksEvents with the chosen event date format', function () {
    $startsAt = Carbon::parse('2026-07-04 17:30:00');

    Event::factory()->create([
        'title'     => 'Tile Event',
        'slug'      => 'tile-event',
        'status'    => 'published',
        'starts_at' => $startsAt,
    ]);

    $wt = WidgetType::where('handle', 'this_weeks_events')->firstOrFail();

    $tile = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['days_ahead' => 7, 'date_format' => DateFormat::EVENT_TILE_DATE],
    ]);
    $tile->setRelation('widgetType', $wt);

    $long = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['days_ahead' => 7, 'date_format' => DateFormat::LONG_DATE],
    ]);
    $long->setRelation('widgetType', $wt);

    expect(WidgetRenderer::render($tile, [], [], 'dashboard_grid')['html'])->toContain('Sat, Jul 4')
        ->and(WidgetRenderer::render($long, [], [], 'dashboard_grid')['html'])->toContain('July 4, 2026');
});

it('falls back to the ThisWeeksEvents default when date_format is unknown', function () {
    $startsAt = Carbon::parse('2026-07-04 17:30:00');

    Event::factory()->create([
        'title'     => 'Fallback Tile',
        'slug'      => 'fallback-tile',
        'status'    => 'published',
        'starts_at' => $startsAt,
    ]);

    $wt = WidgetType::where('handle', 'this_weeks_events')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['days_ahead' => 7, 'date_format' => 'NOT_A_REAL_FORMAT'],
    ]);
    $pw->setRelation('widgetType', $wt);

    expect(WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'])->toContain('Sat, Jul 4');
});

it('renders EventDescription with the chosen event date format', function () {
    $startsAt = Carbon::parse('2026-09-12 17:30:00');

    Event::factory()->create([
        'title'     => 'Described Event',
        'slug'      => 'described-event',
        'status'    => 'published',
        'starts_at' => $startsAt,
        'ends_at'   => $startsAt->copy()->addHours(2),
    ]);

    $wt = WidgetType::where('handle', 'event_description')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'ed-host', 'status' => 'published']);

    $tile = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'described-event', 'date_format' => DateFormat::EVENT_TILE_DATE],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $long = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'described-event', 'date_format' => DateFormat::LONG_DATE],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    expect(WidgetRenderer::render($tile)['html'])->toContain('Sat, Sep 12')
        ->and(WidgetRenderer::render($long)['html'])->toContain('September 12, 2026');
});

it('falls back to the EventDescription default when date_format is unknown', function () {
    $startsAt = Carbon::parse('2026-09-12 17:30:00');

    Event::factory()->create([
        'title'     => 'Bad Hint Described',
        'slug'      => 'bad-hint-described',
        'status'    => 'published',
        'starts_at' => $startsAt,
    ]);

    $wt = WidgetType::where('handle', 'event_description')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'ed-fallback', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['event_slug' => 'bad-hint-described', 'date_format' => 'NOT_A_REAL_FORMAT'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect(WidgetRenderer::render($pw)['html'])->toContain('Sat, Sep 12');
});

it('renders BlogListing with the chosen post date format', function () {
    $publishedAt = Carbon::parse('2026-01-12 10:00:00');

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Post With Date',
        'slug'         => 'post-with-date',
        'status'       => 'published',
        'published_at' => $publishedAt,
    ]);

    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'bl-host', 'status' => 'published']);

    $long = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="d">{{item.post_date}}</p>',
            'date_format'      => DateFormat::LONG_DATE,
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'newest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $medium = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="d">{{item.post_date}}</p>',
            'date_format'      => DateFormat::MEDIUM_DATE,
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'newest',
        ],
        'sort_order' => 1,
        'is_active'  => true,
    ]);

    expect(WidgetRenderer::render($long)['html'])->toContain('January 12, 2026')
        ->and(WidgetRenderer::render($medium)['html'])->toContain('Jan 12, 2026');
});

it('falls back to the BlogListing default when date_format is unknown', function () {
    $publishedAt = Carbon::parse('2026-01-12 10:00:00');

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Bad Hint Post',
        'slug'         => 'bad-hint-post',
        'status'       => 'published',
        'published_at' => $publishedAt,
    ]);

    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'bl-fallback', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => '',
            'content_template' => '<p class="d">{{item.post_date}}</p>',
            'date_format'      => 'NOT_A_REAL_FORMAT',
            'columns'          => 1,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'newest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    expect(WidgetRenderer::render($pw)['html'])->toContain('January 12, 2026');
});

it('renders BlogPager with the chosen post date format', function () {
    $hostPublishedAt = Carbon::parse('2026-04-15 10:00:00');
    $neighborPublishedAt = Carbon::parse('2026-01-15 10:00:00');

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post',
        'slug'         => 'host-post',
        'status'       => 'published',
        'published_at' => $hostPublishedAt,
    ]);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Neighbor Post',
        'slug'         => 'neighbor-post',
        'status'       => 'published',
        'published_at' => $neighborPublishedAt,
    ]);

    $wt = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    app()->instance(PageContext::class, new PageContext($host));

    $long = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['date_format' => DateFormat::LONG_DATE],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect(WidgetRenderer::render($long)['html'])->toContain('January 15, 2026');

    $medium = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['date_format' => DateFormat::MEDIUM_DATE],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    expect(WidgetRenderer::render($medium)['html'])->toContain('Jan 15, 2026');
});

it('falls back to the BlogPager default when date_format is unknown', function () {
    $hostPublishedAt = Carbon::parse('2026-04-15 10:00:00');
    $neighborPublishedAt = Carbon::parse('2026-01-15 10:00:00');

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post',
        'slug'         => 'host-post-fallback',
        'status'       => 'published',
        'published_at' => $hostPublishedAt,
    ]);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Neighbor Post',
        'slug'         => 'neighbor-post-fallback',
        'status'       => 'published',
        'published_at' => $neighborPublishedAt,
    ]);

    $wt = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    app()->instance(PageContext::class, new PageContext($host));

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['date_format' => 'NOT_A_REAL_FORMAT'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect(WidgetRenderer::render($pw)['html'])->toContain('January 15, 2026');
});
