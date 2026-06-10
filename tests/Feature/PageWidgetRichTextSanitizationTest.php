<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

it('sanitises richtext-typed config fields on PageWidget save', function () {
    $page       = Page::factory()->create();
    $textBlock  = WidgetType::where('handle', 'text_block')->firstOrFail();

    $widget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $textBlock->id,
        'label'             => 'Block',
        'config'            => [
            'content' => '<p>visible</p><script>alert(1)</script><a href="javascript:bad">x</a>',
            'vertical_align' => 'middle',
        ],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $stored = $widget->fresh()->config;
    expect($stored['content'])->toBe('<p>visible</p><a>x</a>');
    expect($stored['vertical_align'])->toBe('middle');
});

it('sanitises richtext-typed config fields on PageWidget update', function () {
    $page      = Page::factory()->create();
    $textBlock = WidgetType::where('handle', 'text_block')->firstOrFail();

    $widget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $textBlock->id,
        'label'             => 'Block',
        'config'            => ['content' => '<p>clean</p>', 'vertical_align' => 'middle'],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $widget->update([
        'config' => [
            'content'        => '<p onclick="alert(1)">edited</p>',
            'vertical_align' => 'middle',
        ],
    ]);

    expect($widget->fresh()->config['content'])->toBe('<p>edited</p>');
});

it('renders a blockquote (with cite) authored in a richtext widget through the public render path', function () {
    // The blockquote toolbar control is native to Quill and HtmlSanitizer
    // already allows <blockquote> + <cite>; this guards the end-to-end path —
    // save-time sanitize + render-time RichTextSemantics leave a semantic
    // blockquote/citation intact in the rendered widget output.
    $page      = Page::factory()->create();
    $textBlock = WidgetType::where('handle', 'text_block')->firstOrFail();

    $widget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $textBlock->id,
        'label'             => 'Block',
        'config'            => [
            'content'        => '<blockquote>Stay hungry, stay foolish.<cite>Steve Jobs</cite></blockquote>',
            'vertical_align' => 'middle',
        ],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $html = WidgetRenderer::render($widget->fresh())['html'] ?? '';

    expect($html)
        ->toContain('<blockquote>')
        ->toContain('Stay hungry, stay foolish.')
        ->toContain('<cite>Steve Jobs</cite>');
});

it('leaves non-richtext config fields untouched', function () {
    $page      = Page::factory()->create();
    $textBlock = WidgetType::where('handle', 'text_block')->firstOrFail();

    $widget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $textBlock->id,
        'label'             => 'Block',
        'config'            => [
            'content'        => '<p>safe</p>',
            'vertical_align' => 'top',
        ],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    expect($widget->fresh()->config['vertical_align'])->toBe('top');
});
