<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults $slotHandle to page_builder_canvas and substitutes page-context tokens', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $host = Page::factory()->create([
        'title'        => 'Canvas Host',
        'slug'         => 'canvas-host',
        'status'       => 'published',
        'published_at' => '2026-04-22 00:00:00',
    ]);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['content' => '<p>Hello from {{title}}.</p>'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Canvas Host')
        ->not->toContain('{{title}}');
});

it('explicit page_builder_canvas slot handle matches default byte-equivalently', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $host = Page::factory()->create([
        'title'        => 'Byte Equivalent',
        'slug'         => 'byte-equivalent',
        'status'       => 'published',
        'published_at' => '2026-04-22 00:00:00',
    ]);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['content' => '<p>Hello from {{title}}.</p>'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $default  = WidgetRenderer::render($pw);
    $explicit = WidgetRenderer::render($pw, [], [], 'page_builder_canvas');

    expect($explicit)->toBe($default);
});

it('dashboard_grid slot handle skips token substitution — raw template renders', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => ['content' => '<p>Hello from {{title}}.</p>'],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)->toContain('{{title}}');
});

it('dashboard_grid slot handle resolves SOURCE_SYSTEM_MODEL contracts (blog listing renders posts)', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'blog_listing')->firstOrFail();

    Page::factory()->create([
        'type'         => 'post',
        'title'        => 'Dashboard-Side Post',
        'slug'         => 'dashboard-side-post',
        'status'       => 'published',
        'published_at' => now()->subDay(),
    ]);

    $pw = new PageWidget([
        'widget_type_id' => $wt->id,
        'config'         => [
            'heading'          => 'Dashboard Posts',
            'content_template' => '<article><h3>{{item.title}}</h3></article>',
            'columns'          => 3,
            'items_per_page'   => 3,
        ],
    ]);
    $pw->setRelation('widgetType', $wt);

    $html = WidgetRenderer::render($pw, [], [], 'dashboard_grid')['html'];

    expect($html)
        ->toContain('Dashboard Posts')
        ->toContain('Dashboard-Side Post');
});
