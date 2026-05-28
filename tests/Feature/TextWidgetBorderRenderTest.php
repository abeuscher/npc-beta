<?php

// Session 323 — verify the universal border emits onto the Text widget's
// outer box on the public render path, both standalone and inside a column
// layout. The border value is composed by AppearanceStyleComposer and applied
// to the widget div by PageBlockRenderer; this exercises that whole path on
// the real text_block widget.

use App\Models\Page;
use App\Models\WidgetType;
use App\Services\PageBlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $this->renderer = app(PageBlockRenderer::class);
    $this->textType = WidgetType::where('handle', 'text_block')->firstOrFail();

    $this->page = Page::factory()->create([
        'title'  => 'Border Test Page',
        'slug'   => 'border-test-' . uniqid(),
        'status' => 'published',
    ]);
});

function makeTextWidget(Page $page, WidgetType $type, array $border, ?string $layoutId = null, int $columnIndex = 0)
{
    return $page->widgets()->create([
        'widget_type_id'    => $type->id,
        'layout_id'         => $layoutId,
        'column_index'      => $layoutId ? $columnIndex : null,
        'label'             => 'Bordered Text',
        'config'            => ['content' => '<p>Hello</p>'],
        'query_config'      => [],
        'appearance_config' => ['layout' => ['border' => $border]],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

it('emits the border onto a standalone Text widget block', function () {
    $pw = makeTextWidget($this->page, $this->textType, [
        'top' => true, 'right' => true, 'bottom' => true, 'left' => true,
        'width' => 2, 'color' => '#3366cc', 'radius' => 6,
    ]);

    $block = $this->renderer->renderWidgetBlock($pw);

    expect($block)->not->toBeNull();
    expect($block['block']['inline_style'])
        ->toContain('border-top:2px solid #3366cc')
        ->toContain('border-right:2px solid #3366cc')
        ->toContain('border-bottom:2px solid #3366cc')
        ->toContain('border-left:2px solid #3366cc')
        ->toContain('border-radius:6px')
        ->toContain('box-sizing:border-box');
});

it('emits the border onto a Text widget inside a column layout', function () {
    $layout = $this->page->layouts()->create([
        'label'         => 'Two Col',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [],
        'sort_order'    => 0,
    ]);

    makeTextWidget($this->page, $this->textType, [
        'top' => true, 'right' => false, 'bottom' => false, 'left' => false,
        'width' => 1, 'color' => '#000000', 'radius' => 0,
    ], $layout->id, 0);

    $styles = '';
    $scripts = '';
    $layout->load(['widgets' => fn ($q) => $q->orderBy('sort_order'), 'widgets.widgetType']);
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts);

    expect($block['html'])
        ->toContain('border-top:1px solid #000000')
        ->toContain('box-sizing:border-box');
});

it('leaves a default (all-off) Text widget free of any border style', function () {
    $pw = makeTextWidget($this->page, $this->textType, [
        'top' => false, 'right' => false, 'bottom' => false, 'left' => false,
        'width' => 0, 'color' => '#000000', 'radius' => 0,
    ]);

    $block = $this->renderer->renderWidgetBlock($pw);

    expect($block['block']['inline_style'])
        ->not->toContain('border-')
        ->not->toContain('box-sizing');
});
