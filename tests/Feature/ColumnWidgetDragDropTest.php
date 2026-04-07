<?php

use App\Livewire\PageBuilder;
use App\Livewire\PageBuilderBlock;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

function makeTestUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo('update_page');
    return $user;
}

function makeRootWidget(Page $page, ?string $handle = null, int $sortOrder = 0): PageWidget
{
    $wt = WidgetType::where('handle', $handle ?? 'text_block')->firstOrFail();

    return PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'label'          => $wt->label . ' ' . ($sortOrder + 1),
        'config'         => $wt->getDefaultConfig(),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => $sortOrder,
        'is_active'      => true,
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
// Move to column: root block → column slot
// -------------------------------------------------------------------------

it('moves a root block into a column slot via move-to-column action', function () {
    $page = makeTestPage();
    $user = makeTestUser();

    $column = makeRootWidget($page, 'column_widget', 0);
    $widget = makeRootWidget($page, 'text_block', 1);

    Livewire::actingAs($user)
        ->test(PageBuilder::class, ['pageId' => $page->id])
        ->dispatch('block-move-to-column-requested', blockId: $widget->id, columnWidgetId: $column->id, columnIndex: 0);

    $widget->refresh();
    expect($widget->parent_widget_id)->toBe($column->id)
        ->and($widget->column_index)->toBe(0);
});

// -------------------------------------------------------------------------
// Move to main list: column slot → root level
// -------------------------------------------------------------------------

it('moves a child block to the main list via move-to-main action', function () {
    $page = makeTestPage();
    $user = makeTestUser();

    $column = makeRootWidget($page, 'column_widget', 0);
    $child  = makeChildWidget($page, $column, 0);

    Livewire::actingAs($user)
        ->test(PageBuilder::class, ['pageId' => $page->id])
        ->dispatch('block-move-to-main-requested', blockId: $child->id);

    $child->refresh();
    expect($child->parent_widget_id)->toBeNull()
        ->and($child->column_index)->toBeNull();
});

// -------------------------------------------------------------------------
// Move between columns: column slot → different column slot
// -------------------------------------------------------------------------

it('moves a child block between column slots via move-to-column action', function () {
    $page = makeTestPage();
    $user = makeTestUser();

    $column = makeRootWidget($page, 'column_widget', 0);
    $column->update(['config' => ['num_columns' => 3, 'grid_template_columns' => '1fr 1fr 1fr']]);
    $child = makeChildWidget($page, $column, 0);

    Livewire::actingAs($user)
        ->test(PageBuilderBlock::class, ['blockId' => $column->id])
        ->dispatch('child-move-to-column-requested', childId: $child->id, parentId: $column->id, columnIndex: 2);

    $child->refresh();
    expect($child->column_index)->toBe(2);
});

// -------------------------------------------------------------------------
// Nesting prevention: column widgets cannot be moved into columns
// -------------------------------------------------------------------------

it('rejects moving a column widget into another column widget', function () {
    $page = makeTestPage();
    $user = makeTestUser();

    $column1 = makeRootWidget($page, 'column_widget', 0);
    $column2 = makeRootWidget($page, 'column_widget', 1);

    Livewire::actingAs($user)
        ->test(PageBuilder::class, ['pageId' => $page->id])
        ->dispatch('block-move-to-column-requested', blockId: $column2->id, columnWidgetId: $column1->id, columnIndex: 0);

    $column2->refresh();
    expect($column2->parent_widget_id)->toBeNull();
});

// -------------------------------------------------------------------------
// Column count decrease: widgets in removed slots relocate
// -------------------------------------------------------------------------

it('relocates children to the last remaining slot when column count decreases', function () {
    $page = makeTestPage();
    $user = makeTestUser();

    $column = makeRootWidget($page, 'column_widget', 0);
    $column->update(['config' => array_merge($column->config, ['num_columns' => 3, 'grid_template_columns' => '1fr 1fr 1fr'])]);

    $child0 = makeChildWidget($page, $column, 0, 0);
    $child1 = makeChildWidget($page, $column, 1, 0);
    $child2 = makeChildWidget($page, $column, 2, 0);

    $column->update(['config' => array_merge($column->config, ['num_columns' => 2, 'grid_template_columns' => '1fr 1fr'])]);

    Livewire::actingAs($user)
        ->test(PageBuilderBlock::class, ['blockId' => $column->id])
        ->dispatch('widget-config-updated', blockId: $column->id);

    $child2->refresh();
    expect($child2->column_index)->toBe(1);

    $child0->refresh();
    $child1->refresh();
    expect($child0->column_index)->toBe(0)
        ->and($child1->column_index)->toBe(1);
});

// -------------------------------------------------------------------------
// Public render: column widget outputs correct CSS grid markup
// -------------------------------------------------------------------------

it('renders column widget with CSS grid markup and children', function () {
    $page = makeTestPage();

    $column = makeRootWidget($page, 'column_widget', 0);
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

// -------------------------------------------------------------------------
// Security: updateOrder rejects widget IDs from another page
// -------------------------------------------------------------------------

it('rejects updateOrder when widget IDs belong to a different page', function () {
    $page1 = makeTestPage();
    $page2 = makeTestPage();
    $user  = makeTestUser();

    $widget1 = makeRootWidget($page1, 'text_block', 0);
    $widget2 = makeRootWidget($page2, 'text_block', 0);

    $payload = [
        ['id' => $widget1->id, 'parent_id' => null, 'column_index' => null, 'sort_order' => 0],
        ['id' => $widget2->id, 'parent_id' => null, 'column_index' => null, 'sort_order' => 1],
    ];

    Livewire::actingAs($user)
        ->test(PageBuilder::class, ['pageId' => $page1->id])
        ->call('updateOrder', $payload);

    $widget2->refresh();
    expect($widget2->sort_order)->toBe(0)
        ->and($widget2->page_id)->toBe($page2->id);
});
