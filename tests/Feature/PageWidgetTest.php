<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeWidgetType(array $overrides = []): WidgetType
{
    return WidgetType::create(array_merge([
        'handle'        => 'text_block',
        'label'         => 'Text Block',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [
            ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
        ],
        'template'      => '{!! $config[\'content\'] ?? \'\' !!}',
    ], $overrides));
}

it('can be created and associated with a page', function () {
    $page       = Page::factory()->create([
        'title'        => 'Test Page',
        'slug'         => 'test-page',
        'status' => 'published',
    ]);
    $widgetType = makeWidgetType();

    $widget = $page->widgets()->create([
        'widget_type_id' => $widgetType->id,
        'config'         => ['content' => '<p>Hello</p>'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect($widget->owner_id)->toBe($page->id)
        ->and($widget->owner_type)->toBe((new Page())->getMorphClass())
        ->and($widget->config['content'])->toBe('<p>Hello</p>');
});

// Two framework-echo tests were removed at the s364 test audit (belongsTo
// resolution and null-on-unsaved-model assert Eloquent, not app code).

it('excludes inactive widgets from the public page render', function () {
    // Routes through the real loading path (PageController's is_active filter)
    // rather than re-writing the where() clause inside the test — the previous
    // version asserted its own query, so the production filter could regress
    // without failing anything.
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $page = Page::factory()->create([
        'title'  => 'Test Page',
        'slug'   => 'inactive-widget-test',
        'status' => 'published',
    ]);
    $textType = WidgetType::where('handle', 'text_block')->firstOrFail();

    $page->widgets()->create([
        'widget_type_id' => $textType->id,
        'config'         => ['content' => '<p>ACTIVE-WIDGET-MARKER</p>'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $page->widgets()->create([
        'widget_type_id' => $textType->id,
        'config'         => ['content' => '<p>INACTIVE-WIDGET-MARKER</p>'],
        'sort_order'     => 1,
        'is_active'      => false,
    ]);

    $this->get('/' . $page->slug)
        ->assertOk()
        ->assertSee('ACTIVE-WIDGET-MARKER')
        ->assertDontSee('INACTIVE-WIDGET-MARKER');
});
