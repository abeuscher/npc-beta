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
    $page       = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page',
        'is_published' => true,
    ]);
    $widgetType = makeWidgetType();

    $widget = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => ['content' => '<p>Hello</p>'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect($widget->page_id)->toBe($page->id)
        ->and($widget->config['content'])->toBe('<p>Hello</p>');
});

it('widgetType relationship returns the correct WidgetType model', function () {
    $page       = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page-2',
        'is_published' => true,
    ]);
    $widgetType = makeWidgetType(['handle' => 'text_block_2', 'label' => 'Text Block 2']);

    $widget = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    expect($widget->widgetType)->toBeInstanceOf(WidgetType::class)
        ->and($widget->widgetType->handle)->toBe('text_block_2');
});

it('widgetType returns null when widget_type_id is missing', function () {
    $widget = new PageWidget();

    expect($widget->widgetType)->toBeNull();
});

it('inactive widgets are excluded when loading for a page', function () {
    $page       = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page-4',
        'is_published' => true,
    ]);
    $widgetType = makeWidgetType();

    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => ['content' => 'Active'],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $widgetType->id,
        'config'         => ['content' => 'Inactive'],
        'sort_order'     => 1,
        'is_active'      => false,
    ]);

    $active = $page->pageWidgets()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->config['content'])->toBe('Active');
});
