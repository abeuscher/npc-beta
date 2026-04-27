<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\WidgetType;
use App\Widgets\BlogListing\BlogListingDefinition;
use App\Widgets\EventsListing\EventsListingDefinition;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function blogListingWidgetData(array $config = []): array
{
    $contract = (new BlogListingDefinition())->dataContract($config);
    $slot = new SlotContext(new PageAmbientContext());
    return app(ContractResolver::class)->resolve([$contract], $slot)[0];
}

function eventsListingWidgetData(array $config = []): array
{
    $contract = (new EventsListingDefinition())->dataContract($config);
    $slot = new SlotContext(new PageAmbientContext());
    return app(ContractResolver::class)->resolve([$contract], $slot)[0];
}

// ── Page model media collections ─────────────────────────────────────────────

it('page model registers post_thumbnail and post_header media collections', function () {
    $page = Page::factory()->create(['type' => 'post']);

    $collections = $page->getRegisteredMediaCollections();
    $names = collect($collections)->pluck('name')->all();

    expect($names)->toContain('post_thumbnail')
        ->toContain('post_header');
});

// ── Event model media collections ────────────────────────────────────────────

it('event model registers event_thumbnail media collection', function () {
    $event = Event::factory()->create();

    $collections = $event->getRegisteredMediaCollections();
    $names = collect($collections)->pluck('name')->all();

    expect($names)->toContain('event_thumbnail');
});

// ── Seeder config ────────────────────────────────────────────────────────────

it('blog_listing seeder has correct config schema keys', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'blog_listing')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->category)->toBe(['blog']);

    $keys = collect($wt->config_schema)->pluck('key')->filter()->values()->all();
    expect($keys)->toContain('heading')
        ->toContain('content_template')
        ->toContain('columns')
        ->toContain('items_per_page')
        ->toContain('show_search')
        ->toContain('sort_default')
        ->toContain('gap');

    $gapField = collect($wt->config_schema)->firstWhere('key', 'gap');
    expect($gapField['type'])->toBe('number')
        ->and($gapField['default'])->toBe(24)
        ->and($gapField['group'])->toBe('appearance');
});

it('events_listing seeder has correct config schema keys', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'events_listing')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->category)->toBe(['events']);

    $keys = collect($wt->config_schema)->pluck('key')->filter()->values()->all();
    expect($keys)->toContain('heading')
        ->toContain('content_template')
        ->toContain('columns')
        ->toContain('items_per_page')
        ->toContain('show_search')
        ->toContain('sort_default')
        ->toContain('gap');

    $gapField = collect($wt->config_schema)->firstWhere('key', 'gap');
    expect($gapField['type'])->toBe('number')
        ->and($gapField['default'])->toBe(24)
        ->and($gapField['group'])->toBe('appearance');
});

// ── Blog listing rendering ───────────────────────────────────────────────────

it('blog listing widget renders with default content template', function () {
    // Create published posts
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Test Blog Post',
        'slug'         => 'test-blog-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => 'Latest Posts',
                'content_template' => '<h3>{{item.title}}</h3><time>{{item.date}}</time>',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('Latest Posts')
        ->toContain('Test Blog Post')
        ->toContain('widget-blog-listing');
});

it('blog listing widget applies token replacement correctly', function () {
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Token Test Post',
        'slug'         => 'token-test',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '<span class="test-title">{{item.title}}</span><span class="test-url">{{item.url}}</span>',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('Token Test Post')
        ->toContain('token-test')
        ->not->toContain('{{item.title}}')
        ->not->toContain('{{item.url}}');
});

it('blog listing renders empty state when no posts exist', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)->toContain('No posts found.');
});

it('blog listing renders search input when show_search is enabled', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => true,
                'sort_default'     => 'newest',
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('type="search"')
        ->toContain('Search posts');
});

// ── Events listing rendering ─────────────────────────────────────────────────

it('events listing widget renders with default content template', function () {
    Event::factory()->create([
        'title'     => 'Test Event',
        'slug'      => 'test-event',
        'status'    => 'published',
        'starts_at' => now()->addWeek(),
        'ends_at'   => now()->addWeek()->addHours(2),
        'price'     => 0,
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => 'Upcoming Events',
                'content_template' => '<h3>{{item.title}}</h3><time>{{item.date}}</time>',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'soonest',
            ],
            'widgetData'  => eventsListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('Upcoming Events')
        ->toContain('Test Event')
        ->toContain('widget-events-listing');
});

it('events listing widget applies token replacement correctly', function () {
    Event::factory()->create([
        'title'          => 'Token Event',
        'slug'           => 'token-event',
        'status'         => 'published',
        'starts_at'      => now()->addWeek(),
        'ends_at'        => now()->addWeek()->addHours(2),
        'price'          => 0,
        'address_line_1' => '123 Main St',
        'city'           => 'Springfield',
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '<span class="test-title">{{item.title}}</span><span class="test-loc">{{item.location}}</span>{{item.price_badge}}',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'soonest',
            ],
            'widgetData'  => eventsListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('Token Event')
        ->toContain('123 Main St')
        ->toContain('Free')
        ->not->toContain('{{item.title}}')
        ->not->toContain('{{item.location}}');
});

it('events listing renders empty state when no upcoming events', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'soonest',
            ],
            'widgetData'  => eventsListingWidgetData(),
        ]
    );

    expect($html)->toContain('No upcoming events. Check back soon.');
});

it('events listing renders search input when show_search is enabled', function () {
    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',

                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => true,
                'sort_default'     => 'soonest',
            ],
            'widgetData'  => eventsListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('type="search"')
        ->toContain('Search events');
});

// ── Swiper integration ───────────────────────────────────────────────────────

it('blog listing renders Swiper container with slides', function () {
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Swiper Post',
        'slug'         => 'swiper-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',
                'columns'          => 4,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)
        ->toContain('swiper-wrapper')
        ->toContain('swiper-slide')
        ->toContain('x-ref="btnPrev"')
        ->toContain('x-ref="btnNext"')
        ->toContain('x-ref="pagination"')
        ->toContain('Swiper Post');
});

// ── Gap config field — blog listing ─────────────────────────────────────────

it('blog listing renders default spaceBetween when gap is not set', function () {
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Gap Default Post',
        'slug'         => 'gap-default-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',
                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)->toContain('"gap":24');
});

it('blog listing renders custom spaceBetween from gap config', function () {
    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Gap Custom Post',
        'slug'         => 'gap-custom-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/BlogListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',
                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'newest',
                'gap'              => 32,
            ],
            'widgetData'  => blogListingWidgetData(),
        ]
    );

    expect($html)->toContain('"gap":32');
});

// ── Gap config field — events listing ───────────────────────────────────────

it('events listing renders default spaceBetween when gap is not set', function () {
    Event::factory()->create([
        'title'     => 'Gap Default Event',
        'slug'      => 'gap-default-event',
        'status'    => 'published',
        'starts_at' => now()->addWeek(),
        'ends_at'   => now()->addWeek()->addHours(2),
        'price'     => 0,
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',
                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'soonest',
            ],
            'widgetData'  => eventsListingWidgetData(),
        ]
    );

    expect($html)->toContain('"gap":24');
});

it('events listing renders custom spaceBetween from gap config', function () {
    Event::factory()->create([
        'title'     => 'Gap Custom Event',
        'slug'      => 'gap-custom-event',
        'status'    => 'published',
        'starts_at' => now()->addWeek(),
        'ends_at'   => now()->addWeek()->addHours(2),
        'price'     => 0,
    ]);

    $html = Blade::render(
        file_get_contents(base_path('app/Widgets/EventsListing/template.blade.php')),
        [
            'config'      => [
                'heading'          => '',
                'content_template' => '{{item.title}}',
                'columns'          => 3,
                'items_per_page'   => 10,
                'show_search'      => false,
                'sort_default'     => 'soonest',
                'gap'              => 40,
            ],
            'widgetData'  => eventsListingWidgetData(),
        ]
    );

    expect($html)->toContain('"gap":40');
});
