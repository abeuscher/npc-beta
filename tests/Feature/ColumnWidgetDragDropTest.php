<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function makeTestPage(): Page
{
    return Page::factory()->create([
        'title'  => 'Test Page',
        'slug'   => 'test-column-' . uniqid(),
        'status' => 'published',
    ]);
}

function makeChildWidget(Page $page, PageWidget $parent, int $columnIndex, int $sortOrder = 0): PageWidget
{
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    return PageWidget::create([
        'page_id'          => $page->id,
        'parent_widget_id' => $parent->id,
        'column_index'     => $columnIndex,
        'widget_type_id'   => $wt->id,
        'label'            => 'Child ' . $columnIndex . '-' . $sortOrder,
        'config'           => $wt->getDefaultConfig(),
        'query_config'     => [],
        'style_config'     => [],
        'sort_order'       => $sortOrder,
        'is_active'        => true,
    ]);
}

// -------------------------------------------------------------------------
// Public render: column widget outputs correct CSS grid markup
// -------------------------------------------------------------------------

it('renders column widget with CSS grid markup and children', function () {
    $page = makeTestPage();

    $wt = WidgetType::where('handle', 'column_widget')->firstOrFail();
    $column = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'label'          => 'Column 1',
        'config'         => $wt->getDefaultConfig(),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
    $column->update(['config' => ['num_columns' => 2, 'grid_template_columns' => '1fr 2fr']]);

    $child = makeChildWidget($page, $column, 0, 0);

    $column->load(['children.widgetType']);

    $columnChildren = [];
    foreach ($column->children as $c) {
        $idx = $c->column_index ?? 0;
        $childResult = WidgetRenderer::render($c);
        if ($childResult['html'] !== null) {
            $columnChildren[$idx][] = [
                'handle'       => $c->widgetType->handle,
                'instance_id'  => $c->id,
                'html'         => $childResult['html'],
                'css'          => $c->widgetType->css ?? '',
                'js'           => $c->widgetType->js ?? '',
                'style_config' => $c->style_config ?? [],
                'full_width'   => false,
            ];
        }
    }

    $result = WidgetRenderer::render($column, $columnChildren);

    expect($result['html'])->toContain('widget-columns')
        ->and($result['html'])->toContain('grid-template-columns:1fr 2fr');
});
