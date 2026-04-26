<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function seedListingWidgetTypes(): void
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();
}

// ── Substring-safety invariant (the fix's core assumption) ──────────────────

it('PageContextTokens substitution pattern does not match item-namespaced tokens', function () {
    $out = str_replace('{{title}}', 'PAGE', '{{item.title}} and {{title}}');
    expect($out)->toBe('{{item.title}} and PAGE');
});

// ── EventsListing — per-event title survives page-context collision ─────────

it('events listing renders per-event title when the hosting page title would collide', function () {
    seedListingWidgetTypes();
    $wt = WidgetType::where('handle', 'events_listing')->firstOrFail();

    // Page titled "Events" — would collide with {{title}} at the page level.
    $page = Page::factory()->create([
        'title'        => 'Events',
        'slug'         => 'events',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    Event::factory()->create([
        'title'          => 'Spring Gala',
        'slug'           => 'spring-gala',
        'status'         => 'published',
        'starts_at'      => now()->addWeek(),
        'ends_at'        => now()->addWeek()->addHours(2),
        'price'          => 0,
        'address_line_1' => '500 Main St',
        'city'           => 'Portland',
    ]);

    $pw = $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => 'What\'s on',
            'content_template' => '<article class="card"><h3>{{item.title}}</h3><p>{{item.location}}</p></article>',
            'columns'          => 3,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'soonest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    // Card region: match the <article class="card">...</article> block.
    preg_match('#<article class="card">.*?</article>#s', $html, $m);
    $cardRegion = $m[0] ?? '';

    expect($cardRegion)
        ->toContain('Spring Gala')
        ->not->toContain('<h3>Events</h3>');
});

// ── BlogListing — per-post title survives page-context collision ────────────

it('blog listing renders per-post title when the hosting page title would collide', function () {
    seedListingWidgetTypes();
    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

    $page = Page::factory()->create([
        'title'        => 'News',
        'slug'         => 'news-index',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Board Welcomes New Director',
        'slug'         => 'new-director',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $pw = $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => 'Latest',
            'content_template' => '<article class="card"><h3>{{item.title}}</h3></article>',
            'columns'          => 3,
            'items_per_page'   => 10,
            'show_search'      => false,
            'sort_default'     => 'newest',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    preg_match('#<article class="card">.*?</article>#s', $html, $m);
    $cardRegion = $m[0] ?? '';

    expect($cardRegion)
        ->toContain('Board Welcomes New Director')
        ->not->toContain('<h3>News</h3>');
});

// ── BlogPager — per-neighbor title survives page-context collision ─────────

it('blog pager renders per-neighbor title when the hosting post title would collide', function () {
    seedListingWidgetTypes();
    $wt = WidgetType::where('handle', 'blog_pager')->firstOrFail();

    $author = User::factory()->create(['name' => 'Author One']);

    $newer = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Newer Post',
        'slug'         => 'newer-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
        'author_id'    => $author->id,
    ]);

    $host = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Host Post',
        'slug'         => 'host-post',
        'status'       => 'published',
        'published_at' => now()->subDays(2),
        'author_id'    => $author->id,
    ]);

    $older = Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Older Post',
        'slug'         => 'older-post',
        'status'       => 'published',
        'published_at' => now()->subDays(3),
        'author_id'    => $author->id,
    ]);

    app()->instance(PageContext::class, new PageContext($host));

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Newer Post')
        ->toContain('Older Post')
        ->not->toContain('&larr; Host Post')
        ->not->toContain('Host Post &rarr;');
});

// ── Carousel — per-slide caption survives page-context collision ────────────

it('carousel renders per-slide caption when the hosting page title would collide', function () {
    seedListingWidgetTypes();
    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();

    $page = Page::factory()->create([
        'title'        => 'Gallery',
        'slug'         => 'gallery-page',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $pw = $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'slides',
            'image_field'       => '',
            'caption_template'  => '{{item.title}}',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    // Feed a fallback "slides" collection directly to WidgetRenderer.
    $fallback = [
        'slides' => [
            ['title' => 'First Slide Caption', '_media' => []],
            ['title' => 'Second Slide Caption', '_media' => []],
        ],
    ];

    $html = WidgetRenderer::render($pw, [], $fallback)['html'];

    expect($html)
        ->toContain('First Slide Caption')
        ->toContain('Second Slide Caption')
        ->not->toContain('>Gallery<');
});
