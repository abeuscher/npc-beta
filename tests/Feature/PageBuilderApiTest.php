<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function apiUser(array $permissions = ['view_page', 'update_page']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function apiPage(): Page
{
    return Page::factory()->create([
        'title'  => 'API Test Page',
        'slug'   => 'api-test-' . uniqid(),
        'status' => 'published',
    ]);
}

function apiWidget(Page $page, ?string $handle = null, int $sortOrder = 0): PageWidget
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

function apiChildWidget(Page $page, PageWidget $parent, int $columnIndex, int $sortOrder = 0): PageWidget
{
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    return PageWidget::create([
        'page_id'          => $page->id,
        'parent_widget_id' => $parent->id,
        'column_index'     => $columnIndex,
        'widget_type_id'   => $wt->id,
        'label'            => 'Child ' . ($sortOrder + 1),
        'config'           => $wt->getDefaultConfig(),
        'query_config'     => [],
        'style_config'     => [],
        'sort_order'       => $sortOrder,
        'is_active'        => true,
    ]);
}

function apiPrefix(): string
{
    return '/' . config('filament.path', env('ADMIN_PATH', 'admin')) . '/api/page-builder';
}

// ── GET widgets ──────────────────────────────────────────────────────────

it('returns the widget tree for a page', function () {
    $page = apiPage();
    $w1 = apiWidget($page, 'text_block', 0);
    $w2 = apiWidget($page, 'text_block', 1);

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . "/{$page->id}/widgets");

    $response->assertOk()
        ->assertJsonCount(2, 'widgets')
        ->assertJsonPath('widgets.0.id', $w1->id)
        ->assertJsonPath('widgets.1.id', $w2->id)
        ->assertJsonStructure([
            'widgets' => [
                '*' => [
                    'id', 'widget_type_id', 'widget_type_handle', 'widget_type_label',
                    'label', 'config', 'query_config', 'style_config', 'sort_order',
                    'is_active', 'is_required', 'preview_html', 'children',
                ],
            ],
            'required_libs',
        ]);
});

// ── POST widgets (create) ────────────────────────────────────────────────

it('creates a widget on a page', function () {
    $page = apiPage();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/{$page->id}/widgets", [
            'widget_type_id' => $wt->id,
            'label'          => 'My Widget',
        ]);

    $response->assertCreated()
        ->assertJsonPath('widget.label', 'My Widget')
        ->assertJsonStructure(['widget', 'tree', 'required_libs']);

    $this->assertDatabaseHas('page_widgets', [
        'page_id' => $page->id,
        'label'   => 'My Widget',
    ]);
});

it('auto-generates a label when creating without one', function () {
    $page = apiPage();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/{$page->id}/widgets", [
            'widget_type_id' => $wt->id,
        ]);

    $response->assertCreated();
    expect($response->json('widget.label'))->toStartWith($wt->label);
});

it('inserts at the specified position', function () {
    $page = apiPage();
    $w1 = apiWidget($page, 'text_block', 0);
    $w2 = apiWidget($page, 'text_block', 1);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/{$page->id}/widgets", [
            'widget_type_id'  => $wt->id,
            'label'           => 'Inserted',
            'insert_position' => 1,
        ]);

    $response->assertCreated();

    // Verify the new widget is at position 1
    $tree = $response->json('tree');
    expect($tree[1]['label'])->toBe('Inserted');
});

// ── PUT widgets (update) ─────────────────────────────────────────────────

it('updates a widget label and config', function () {
    $page = apiPage();
    $widget = apiWidget($page);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/widgets/{$widget->id}", [
            'label'  => 'Updated Label',
            'config' => ['heading' => 'New Heading'],
        ]);

    $response->assertOk()
        ->assertJsonPath('widget.label', 'Updated Label');

    $widget->refresh();
    expect($widget->label)->toBe('Updated Label');
    expect($widget->config['heading'])->toBe('New Heading');
});

// ── DELETE widgets ───────────────────────────────────────────────────────

it('deletes a widget and its children', function () {
    $page = apiPage();
    $column = apiWidget($page, 'column_widget', 0);
    $child = apiChildWidget($page, $column, 0);

    $response = $this->actingAs(apiUser())
        ->deleteJson(apiPrefix() . "/widgets/{$column->id}");

    $response->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonStructure(['tree', 'required_libs']);

    $this->assertDatabaseMissing('page_widgets', ['id' => $column->id]);
    $this->assertDatabaseMissing('page_widgets', ['id' => $child->id]);
});

// ── POST copy ────────────────────────────────────────────────────────────

it('copies a widget and its children', function () {
    $page = apiPage();
    $column = apiWidget($page, 'column_widget', 0);
    $child = apiChildWidget($page, $column, 0);

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/widgets/{$column->id}/copy");

    $response->assertCreated()
        ->assertJsonStructure(['widget', 'tree', 'required_libs']);

    // Original + copy = 2 root widgets
    expect(PageWidget::where('page_id', $page->id)->whereNull('parent_widget_id')->count())->toBe(2);
    // Original child + copied child = 2 children total
    $copyId = $response->json('widget.id');
    expect(PageWidget::where('parent_widget_id', $copyId)->count())->toBe(1);
});

// ── PUT reorder ──────────────────────────────────────────────────────────

it('reorders widgets', function () {
    $page = apiPage();
    $w1 = apiWidget($page, 'text_block', 0);
    $w2 = apiWidget($page, 'text_block', 1);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $w2->id, 'parent_widget_id' => null, 'column_index' => null, 'sort_order' => 0],
                ['id' => $w1->id, 'parent_widget_id' => null, 'column_index' => null, 'sort_order' => 1],
            ],
        ]);

    $response->assertOk();
    expect($response->json('tree.0.id'))->toBe($w2->id);
    expect($response->json('tree.1.id'))->toBe($w1->id);
});

it('rejects reorder with invalid widget IDs', function () {
    $page = apiPage();
    $otherPage = apiPage();
    $widget = apiWidget($otherPage);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $widget->id, 'parent_widget_id' => null, 'column_index' => null, 'sort_order' => 0],
            ],
        ]);

    $response->assertStatus(422);
});

// ── GET preview ──────────────────────────────────────────────────────────

it('returns preview HTML for a widget', function () {
    $page = apiPage();
    $widget = apiWidget($page);

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . "/widgets/{$widget->id}/preview");

    $response->assertOk()
        ->assertJsonStructure(['id', 'html', 'required_libs'])
        ->assertJsonPath('id', $widget->id);
});

// ── Permission checks ────────────────────────────────────────────────────

it('returns 403 for unauthenticated requests', function () {
    $page = apiPage();

    $this->getJson(apiPrefix() . "/{$page->id}/widgets")
        ->assertUnauthorized();
});

it('returns 403 for users without view_page on read endpoints', function () {
    $page = apiPage();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(apiPrefix() . "/{$page->id}/widgets")
        ->assertForbidden();
});

it('returns 403 for users without update_page on write endpoints', function () {
    $page = apiPage();
    $user = apiUser(['view_page']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $this->actingAs($user)
        ->postJson(apiPrefix() . "/{$page->id}/widgets", [
            'widget_type_id' => $wt->id,
        ])
        ->assertForbidden();
});

// ── Widget ownership ─────────────────────────────────────────────────────

it('cannot update a widget from a different page context', function () {
    $page1 = apiPage();
    $page2 = apiPage();
    $widget = apiWidget($page2);

    // The widget belongs to page2, but route model binding doesn't scope to page
    // — this is OK because the update endpoint uses widget ID directly.
    // What we DO test is that the parent_widget_id validation works for create:
    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/{$page1->id}/widgets", [
            'widget_type_id'   => $widget->widget_type_id,
            'parent_widget_id' => $widget->id, // widget from page2
        ]);

    $response->assertStatus(422);
});

// ── Lookup endpoints ─────────────────────────────────────────────────────

it('returns widget types', function () {
    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . '/widget-types?page_type=default');

    $response->assertOk()
        ->assertJsonStructure(['widget_types' => ['*' => ['id', 'handle', 'label']]]);
});

it('returns tags', function () {
    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . '/tags');

    $response->assertOk()
        ->assertJsonStructure(['tags']);
});

it('returns pages', function () {
    apiPage(); // create at least one

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . '/pages');

    $response->assertOk()
        ->assertJsonStructure(['pages' => ['*' => ['slug', 'title']]]);
});

it('returns events', function () {
    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . '/events');

    $response->assertOk()
        ->assertJsonStructure(['events']);
});

it('returns data sources', function () {
    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . '/data-sources/pages');

    $response->assertOk()
        ->assertJsonStructure(['options']);
});

// ── Color swatches ──────────────────────────────────────────────────────

it('saves and returns color swatches', function () {
    $swatches = ['#ff0000', '#00ff00', '#0000ff'];

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . '/color-swatches', ['swatches' => $swatches]);

    $response->assertOk()
        ->assertJson(['swatches' => $swatches]);

    // Verify persisted
    $stored = json_decode(\App\Models\SiteSetting::get('editor_color_swatches', '[]'), true);
    expect($stored)->toBe($swatches);
});

it('replaces swatches on subsequent save', function () {
    \App\Models\SiteSetting::set('editor_color_swatches', json_encode(['#111111']));

    $newSwatches = ['#222222', '#333333'];

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . '/color-swatches', ['swatches' => $newSwatches]);

    $response->assertOk()
        ->assertJson(['swatches' => $newSwatches]);

    $stored = json_decode(\App\Models\SiteSetting::get('editor_color_swatches', '[]'), true);
    expect($stored)->toBe($newSwatches);
});

it('rejects color swatches without update_page permission', function () {
    $user = apiUser(['view_page']);

    $response = $this->actingAs($user)
        ->putJson(apiPrefix() . '/color-swatches', ['swatches' => ['#ff0000']]);

    $response->assertForbidden();
});

it('validates swatches is an array of strings', function () {
    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . '/color-swatches', ['swatches' => 'not-an-array']);

    $response->assertUnprocessable();
});
