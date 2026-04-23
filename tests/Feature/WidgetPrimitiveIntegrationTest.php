<?php

use App\Models\Page;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders a carousel widget through a widget-declared content type contract', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();

    $collection = \App\Models\Collection::create([
        'handle'      => 'e2e-slides',
        'name'        => 'E2E Slides',
        'source_type' => 'custom',
        'is_public'   => true,
        'is_active'   => true,
        'fields'      => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'image', 'type' => 'image'],
        ],
    ]);

    \App\Models\CollectionItem::create([
        'collection_id' => $collection->id,
        'sort_order'    => 0,
        'is_published'  => true,
        'data'          => ['title' => 'Contract Slide One'],
    ]);

    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'c-host', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'e2e-slides',
            'image_field'       => 'image',
            'caption_template'  => '{{item.title}}',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->toContain('Contract Slide One');
});

it('falls back to fallbackCollectionData when a carousel contract resolves to no items', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();

    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'c-host-fallback', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'collection_handle' => 'slides',
            'image_field'       => '',
            'caption_template'  => '{{item.title}}',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $fallback = [
        'slides' => [
            ['title' => 'Fallback Slide A', '_media' => []],
            ['title' => 'Fallback Slide B', '_media' => []],
        ],
    ];

    $html = WidgetRenderer::render($pw, [], $fallback)['html'];

    expect($html)
        ->toContain('Fallback Slide A')
        ->toContain('Fallback Slide B');
});

it('renders a text block widget with page-context tokens substituted via the contract', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $host = Page::factory()->create([
        'title'        => 'Welcome Home',
        'slug'         => 'welcome',
        'status'       => 'published',
        'published_at' => '2026-04-22 00:00:00',
    ]);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'content' => '<p>Hello from {{title}} on {{date}}.</p>',
        ],
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Welcome Home')
        ->toContain('April 22, 2026')
        ->not->toContain('{{title}}')
        ->not->toContain('{{date}}');
});

it('renders a blog listing widget end-to-end through the contract resolver', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'End-to-End Post',
        'slug'         => 'end-to-end-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $host = Page::factory()->create(['title' => 'Host', 'slug' => 'host', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => 'E2E Heading',
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

    expect($html)
        ->toContain('E2E Heading')
        ->toContain('End-to-End Post')
        ->toContain('widget-blog-listing');
});
