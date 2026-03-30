<?php

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Event JSON endpoint ──────────────────────────────────────────────────────

it('event json endpoint returns published events with correct fields', function () {
    $event = Event::factory()->create([
        'status'      => 'published',
        'title'       => 'Community Meetup',
        'description' => 'A great event for the community.',
        'starts_at'   => now()->addDays(3),
        'ends_at'     => now()->addDays(3)->addHours(2),
    ]);

    $response = $this->getJson('/api/events.json');

    $response->assertOk();
    $response->assertJsonFragment(['title' => 'Community Meetup']);

    $first = collect($response->json())->firstWhere('title', 'Community Meetup');
    expect($first)->toHaveKeys(['id', 'title', 'from', 'to', 'description', 'url']);
});

it('event json endpoint excludes draft events', function () {
    Event::factory()->draft()->create(['title' => 'Draft Event', 'starts_at' => now()->addDays(1)]);
    Event::factory()->create(['status' => 'published', 'title' => 'Live Event', 'starts_at' => now()->addDays(1)]);

    $response = $this->getJson('/api/events.json');

    $titles = collect($response->json())->pluck('title');
    expect($titles)->toContain('Live Event')
        ->not->toContain('Draft Event');
});

it('event json endpoint excludes cancelled events', function () {
    Event::factory()->cancelled()->create(['title' => 'Cancelled Event', 'starts_at' => now()->addDays(1)]);

    $response = $this->getJson('/api/events.json');

    $titles = collect($response->json())->pluck('title');
    expect($titles)->not->toContain('Cancelled Event');
});

it('event json endpoint does not expose internal fields', function () {
    Event::factory()->create([
        'status'    => 'published',
        'starts_at' => now()->addDays(1),
    ]);

    $response = $this->getJson('/api/events.json');

    $first = $response->json()[0];
    expect($first)->not->toHaveKeys(['author_id', 'custom_fields', 'registration_mode', 'capacity', 'price']);
});

// ── Video embed URL parsing ──────────────────────────────────────────────────

it('parses youtube watch url into nocookie embed url', function () {
    $html = view('widgets.video-embed', [
        'config' => [
            'video_url'       => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'show_related'    => false,
            'modest_branding' => true,
            'show_controls'   => true,
        ],
        'configMedia'    => [],
        'collectionData' => [],
    ])->render();

    expect($html)->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->toContain('rel=0')
        ->toContain('autoplay=0')
        ->toContain('<iframe')
        ->toContain('padding-bottom: 56.25%');
});

it('parses youtube short url into nocookie embed url', function () {
    $html = view('widgets.video-embed', [
        'config' => [
            'video_url'       => 'https://youtu.be/dQw4w9WgXcQ',
            'show_related'    => false,
            'modest_branding' => true,
            'show_controls'   => true,
        ],
        'configMedia'    => [],
        'collectionData' => [],
    ])->render();

    expect($html)->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ');
});

it('parses vimeo url into embed url', function () {
    $html = view('widgets.video-embed', [
        'config' => [
            'video_url'       => 'https://vimeo.com/123456789',
            'show_related'    => false,
            'modest_branding' => true,
            'show_controls'   => true,
        ],
        'configMedia'    => [],
        'collectionData' => [],
    ])->render();

    expect($html)->toContain('player.vimeo.com/video/123456789')
        ->toContain('autoplay=0')
        ->toContain('<iframe')
        ->toContain('padding-bottom: 56.25%');
});

it('shows fallback for unsupported video url', function () {
    $html = view('widgets.video-embed', [
        'config' => [
            'video_url'       => 'https://example.com/video/123',
            'show_related'    => false,
            'modest_branding' => true,
            'show_controls'   => true,
        ],
        'configMedia'    => [],
        'collectionData' => [],
    ])->render();

    expect($html)->toContain('Unsupported video URL')
        ->not->toContain('<iframe');
});

it('does not produce iframe for arbitrary urls', function () {
    $html = view('widgets.video-embed', [
        'config' => [
            'video_url'       => 'https://evil.com/xss?v=dQw4w9WgXcQ',
            'show_related'    => false,
            'modest_branding' => true,
            'show_controls'   => true,
        ],
        'configMedia'    => [],
        'collectionData' => [],
    ])->render();

    expect($html)->not->toContain('<iframe')
        ->not->toContain('evil.com');
});

// ── Widget type seeder ───────────────────────────────────────────────────────

it('seeder creates event_calendar, bar_chart, and video_embed widget types', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    expect(WidgetType::where('handle', 'event_calendar')->exists())->toBeTrue()
        ->and(WidgetType::where('handle', 'bar_chart')->exists())->toBeTrue()
        ->and(WidgetType::where('handle', 'video_embed')->exists())->toBeTrue();
});

// ── Bar chart widget ─────────────────────────────────────────────────────────

it('bar chart widget renders a canvas element with collection data', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $collection = Collection::create([
        'name'        => 'Test Data',
        'handle'      => 'test-data',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
            ['key' => 'value', 'type' => 'text', 'label' => 'Value'],
        ],
    ]);

    CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['label' => 'January', 'value' => '100'],
        'sort_order'    => 0,
        'is_published'  => true,
    ]);

    CollectionItem::create([
        'collection_id' => $collection->id,
        'data'          => ['label' => 'February', 'value' => '200'],
        'sort_order'    => 1,
        'is_published'  => true,
    ]);

    $page = Page::factory()->create(['slug' => 'chart-test', 'status' => 'published']);

    $widgetType = WidgetType::where('handle', 'bar_chart')->first();

    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => [
            'heading'           => 'Monthly Data',
            'collection_handle' => 'test-data',
            'x_field'           => 'label',
            'y_field'           => 'value',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/chart-test');

    $response->assertOk();
    $response->assertSee('<canvas', false);
    $response->assertSee('Monthly Data');
    $response->assertSee('January');
    $response->assertSee('February');
});
