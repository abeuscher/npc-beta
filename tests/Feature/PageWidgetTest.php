<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Widgets\RichTextWidget;
use App\Widgets\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Ensure widgets are registered for tests
    WidgetRegistry::register([
        \App\Widgets\CollectionListWidget::class,
        \App\Widgets\BlogRollWidget::class,
        \App\Widgets\RichTextWidget::class,
    ]);
});

it('can be created and associated with a page', function () {
    $page = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page',
        'is_published' => true,
    ]);

    $widget = PageWidget::create([
        'page_id'     => $page->id,
        'widget_type' => 'rich_text',
        'config'      => ['content' => '<p>Hello</p>'],
        'sort_order'  => 0,
        'is_active'   => true,
    ]);

    expect($widget->page_id)->toBe($page->id)
        ->and($widget->config['content'])->toBe('<p>Hello</p>');
});

it('typeInstance returns the correct widget class', function () {
    $page = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page-2',
        'is_published' => true,
    ]);

    $widget = PageWidget::create([
        'page_id'     => $page->id,
        'widget_type' => 'rich_text',
        'config'      => ['content' => 'hello'],
        'sort_order'  => 0,
        'is_active'   => true,
    ]);

    expect($widget->typeInstance())->toBeInstanceOf(RichTextWidget::class);
});

it('typeInstance returns null for an unregistered type', function () {
    $page = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page-3',
        'is_published' => true,
    ]);

    $widget = PageWidget::create([
        'page_id'     => $page->id,
        'widget_type' => 'nonexistent_type',
        'config'      => [],
        'sort_order'  => 0,
        'is_active'   => true,
    ]);

    expect($widget->typeInstance())->toBeNull();
});

it('inactive widgets are excluded when loading for a page', function () {
    $page = Page::create([
        'title'        => 'Test Page',
        'slug'         => 'test-page-4',
        'is_published' => true,
    ]);

    PageWidget::create([
        'page_id'     => $page->id,
        'widget_type' => 'rich_text',
        'config'      => ['content' => 'Active'],
        'sort_order'  => 0,
        'is_active'   => true,
    ]);

    PageWidget::create([
        'page_id'     => $page->id,
        'widget_type' => 'rich_text',
        'config'      => ['content' => 'Inactive'],
        'sort_order'  => 1,
        'is_active'   => false,
    ]);

    $active = $page->pageWidgets()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->config['content'])->toBe('Active');
});
