<?php

use App\Models\Page;
use App\Models\PageLayout;
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

    return $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'label'          => $wt->label . ' ' . ($sortOrder + 1),
        'config'         => $wt->getDefaultConfig(),
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => $sortOrder,
        'is_active'      => true,
    ]);
}

function apiLayout(Page $page, int $sortOrder = 0): PageLayout
{
    return $page->layouts()->create([
        'label'         => 'Test Layout',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '1fr 1fr', 'gap' => '1rem'],
        'sort_order'    => $sortOrder,
    ]);
}

function apiChildWidget(Page $page, PageLayout $layout, int $columnIndex, int $sortOrder = 0): PageWidget
{
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    return $page->widgets()->create([
        'layout_id'      => $layout->id,
        'column_index'   => $columnIndex,
        'widget_type_id' => $wt->id,
        'label'          => 'Child ' . ($sortOrder + 1),
        'config'         => $wt->getDefaultConfig(),
        'query_config'   => [],
        'appearance_config' => [],
        'sort_order'     => $sortOrder,
        'is_active'      => true,
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
        ->getJson(apiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk()
        ->assertJsonCount(2, 'widgets')
        ->assertJsonPath('widgets.0.id', $w1->id)
        ->assertJsonPath('widgets.1.id', $w2->id)
        ->assertJsonStructure([
            'widgets' => [
                '*' => [
                    'id', 'widget_type_id', 'widget_type_handle', 'widget_type_label',
                    'label', 'config', 'query_config', 'appearance_config', 'sort_order',
                    'is_active', 'is_required', 'preview_html',
                ],
            ],
            'required_libs',
        ]);
});

it('wraps root widget previews in .site-container but not column children', function () {
    $page = apiPage();

    $rootWidget = apiWidget($page, 'text_block', 0);
    $rootWidget->update(['config' => ['content' => '<p>Root content</p>']]);

    $layout = apiLayout($page, 1);
    $childWidget = apiChildWidget($page, $layout, 0, 0);
    $childWidget->update(['config' => ['content' => '<p>Column content</p>']]);

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();

    $items = $response->json('items');
    $rootPreview  = collect($items)->firstWhere('id', $rootWidget->id)['preview_html'];
    $layoutItem   = collect($items)->firstWhere('type', 'layout');
    $childPreview = $layoutItem['slots']['0'][0]['preview_html'];

    expect($rootPreview)->toContain('site-container');
    expect($childPreview)->not->toContain('site-container');
});

// ── POST widgets (create) ────────────────────────────────────────────────

it('creates a widget on a page', function () {
    $page = apiPage();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/pages/{$page->id}/widgets", [
            'widget_type_id' => $wt->id,
            'label'          => 'My Widget',
        ]);

    $response->assertCreated()
        ->assertJsonPath('widget.label', 'My Widget')
        ->assertJsonStructure(['widget', 'tree', 'required_libs']);

    $this->assertDatabaseHas('page_widgets', [
        'owner_type' => (new \App\Models\Page())->getMorphClass(),
        'owner_id'   => $page->id,
        'label'      => 'My Widget',
    ]);
});

it('auto-generates a label when creating without one', function () {
    $page = apiPage();
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/pages/{$page->id}/widgets", [
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
        ->postJson(apiPrefix() . "/pages/{$page->id}/widgets", [
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
            'config' => ['content' => '<p>New Content</p>'],
        ]);

    $response->assertOk()
        ->assertJsonPath('widget.label', 'Updated Label');

    $widget->refresh();
    expect($widget->label)->toBe('Updated Label');
    expect($widget->config['content'])->toBe('<p>New Content</p>');
});

it('strips config keys that are not declared in the widget schema', function () {
    $page = apiPage();
    $widget = apiWidget($page);

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/widgets/{$widget->id}", [
            'config' => [
                'content'   => '<p>Keep me</p>',
                'junk_key'  => 'drop me',
                'evil_blob' => ['nested' => true],
            ],
        ])->assertOk();

    $widget->refresh();
    expect($widget->config)->toHaveKey('content');
    expect($widget->config)->not->toHaveKey('junk_key');
    expect($widget->config)->not->toHaveKey('evil_blob');
});

it('strips appearance_config keys outside the background/text/layout whitelist', function () {
    $page = apiPage();
    $widget = apiWidget($page);

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/widgets/{$widget->id}", [
            'appearance_config' => [
                'background' => ['color' => '#abcdef'],
                'rogue_key'  => 'drop me',
            ],
        ])->assertOk();

    $widget->refresh();
    expect($widget->appearance_config)->toHaveKey('background');
    expect($widget->appearance_config)->not->toHaveKey('rogue_key');
});

it('strips query_config slots that are not declared on the widget type', function () {
    $page = apiPage();
    $widget = apiWidget($page, 'carousel');

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/widgets/{$widget->id}", [
            'query_config' => [
                'slides'    => ['limit' => 5],
                'phantom'   => ['limit' => 99],
            ],
        ])->assertOk();

    $widget->refresh();
    expect($widget->query_config)->toHaveKey('slides');
    expect($widget->query_config)->not->toHaveKey('phantom');
});

it('update endpoint contract that the JS selective merge depends on', function () {
    // Locks in the response shape that Fix 1.2 (session 163) relies on. The
    // editor store skips merging the user-mutable fields (config,
    // appearance_config, query_config, label) when the widget is dirty, so the
    // server response must:
    //   - return those user-mutable fields exactly as the client sent them
    //     (no server-side merging),
    //   - omit preview_html (the preview is fetched separately by
    //     refreshPreview, not piggybacked onto the update response),
    //   - include the server-authoritative metadata fields that the client
    //     unconditionally merges.
    $page = apiPage();
    $widget = apiWidget($page, 'carousel');

    $sentConfig = ['collection_handle' => 'whatever'];
    $sentAppearance = ['background' => ['color' => '#abcdef']];
    $sentQuery = ['slides' => ['limit' => 5]];

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/widgets/{$widget->id}", [
            'label'             => 'Echo Label',
            'config'            => $sentConfig,
            'appearance_config' => $sentAppearance,
            'query_config'      => $sentQuery,
        ]);

    $response->assertOk();

    // User-mutable fields are echoed back exactly as sent.
    $response->assertJsonPath('widget.label', 'Echo Label');
    $response->assertJsonPath('widget.config.collection_handle', 'whatever');
    $response->assertJsonPath('widget.appearance_config.background.color', '#abcdef');
    $response->assertJsonPath('widget.query_config.slides.limit', 5);

    // Confirm no server-side merging — the returned config has exactly the
    // keys we sent (no leftover defaults from the original create).
    $returnedConfig = $response->json('widget.config');
    expect(array_keys($returnedConfig))->toEqualCanonicalizing(array_keys($sentConfig));

    // Server-authoritative fields the JS always merges.
    $response->assertJsonStructure([
        'widget' => [
            'id',
            'widget_type_id',
            'widget_type_handle',
            'widget_type_label',
            'widget_type_collections',
            'widget_type_config_schema',
            'widget_type_assets',
            'widget_type_default_open',
            'widget_type_required_config',
            'layout_id',
            'column_index',
            'sort_order',
            'is_active',
            'is_required',
            'image_urls',
        ],
    ]);

    // preview_html is intentionally NOT in the update response — the JS fetches
    // it via refreshPreview, and that round trip is what clears the dirty bit.
    expect($response->json('widget'))->not->toHaveKey('preview_html');
});

// ── DELETE widgets ───────────────────────────────────────────────────────

it('deletes a widget', function () {
    $page = apiPage();
    $widget = apiWidget($page, 'text_block', 0);

    $response = $this->actingAs(apiUser())
        ->deleteJson(apiPrefix() . "/widgets/{$widget->id}");

    $response->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonStructure(['tree', 'required_libs']);

    $this->assertDatabaseMissing('page_widgets', ['id' => $widget->id]);
});

// ── POST copy ────────────────────────────────────────────────────────────

it('copies a widget', function () {
    $page = apiPage();
    $widget = apiWidget($page, 'text_block', 0);

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/widgets/{$widget->id}/copy");

    $response->assertCreated()
        ->assertJsonStructure(['widget', 'tree', 'required_libs']);

    // Original + copy = 2 root widgets
    expect(PageWidget::forOwner($page)->whereNull('layout_id')->count())->toBe(2);
});

// ── PUT reorder ──────────────────────────────────────────────────────────

it('reorders widgets', function () {
    $page = apiPage();
    $w1 = apiWidget($page, 'text_block', 0);
    $w2 = apiWidget($page, 'text_block', 1);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/pages/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $w2->id, 'type' => 'widget', 'layout_id' => null, 'column_index' => null, 'sort_order' => 0],
                ['id' => $w1->id, 'type' => 'widget', 'layout_id' => null, 'column_index' => null, 'sort_order' => 1],
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
        ->putJson(apiPrefix() . "/pages/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $widget->id, 'type' => 'widget', 'layout_id' => null, 'column_index' => null, 'sort_order' => 0],
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

    $this->getJson(apiPrefix() . "/pages/{$page->id}/widgets")
        ->assertUnauthorized();
});

it('returns 403 for users without view_page on read endpoints', function () {
    $page = apiPage();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(apiPrefix() . "/pages/{$page->id}/widgets")
        ->assertForbidden();
});

it('returns 403 for users without update_page on write endpoints', function () {
    $page = apiPage();
    $user = apiUser(['view_page']);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $this->actingAs($user)
        ->postJson(apiPrefix() . "/pages/{$page->id}/widgets", [
            'widget_type_id' => $wt->id,
        ])
        ->assertForbidden();
});

// ── Widget ownership ─────────────────────────────────────────────────────

it('cannot create a widget referencing a layout from a different page', function () {
    $page1 = apiPage();
    $page2 = apiPage();
    $layout = apiLayout($page2);
    $wt = WidgetType::where('handle', 'text_block')->firstOrFail();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/pages/{$page1->id}/widgets", [
            'widget_type_id' => $wt->id,
            'layout_id'      => $layout->id, // layout from page2
        ]);

    $response->assertStatus(422);
});

// ── Layout CRUD ──────────────────────────────────────────────────────────

it('creates a layout on a page', function () {
    $page = apiPage();

    $response = $this->actingAs(apiUser())
        ->postJson(apiPrefix() . "/pages/{$page->id}/layouts", [
            'label'   => 'Two Column',
            'display' => 'grid',
            'columns' => 2,
        ]);

    $response->assertCreated()
        ->assertJsonPath('layout.label', 'Two Column')
        ->assertJsonPath('layout.display', 'grid')
        ->assertJsonPath('layout.columns', 2)
        ->assertJsonStructure(['layout', 'items', 'required_libs']);

    $this->assertDatabaseHas('page_layouts', [
        'owner_type' => (new \App\Models\Page())->getMorphClass(),
        'owner_id'   => $page->id,
        'label'      => 'Two Column',
    ]);
});

it('updates layout config', function () {
    $page = apiPage();
    $layout = apiLayout($page);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/layouts/{$layout->id}", [
            'label'         => 'Updated Layout',
            'display'       => 'flex',
            'columns'       => 3,
            'layout_config' => [
                'gap' => '2rem',
                'justify_content' => 'space-between',
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('layout.label', 'Updated Layout')
        ->assertJsonPath('layout.display', 'flex')
        ->assertJsonPath('layout.columns', 3)
        ->assertJsonPath('layout.layout_config.gap', '2rem');
});

it('sanitizes unknown layout_config keys', function () {
    $page = apiPage();
    $layout = apiLayout($page);

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/layouts/{$layout->id}", [
            'layout_config' => [
                'gap'           => '1rem',
                'evil_property' => '<script>alert(1)</script>',
            ],
        ])
        ->assertOk();

    $layout->refresh();
    expect($layout->layout_config)->toHaveKey('gap');
    expect($layout->layout_config)->not->toHaveKey('evil_property');
});

it('merges partial layout_config updates with existing keys', function () {
    $page = apiPage();
    $layout = apiLayout($page);

    // apiLayout starts with grid_template_columns + gap
    expect($layout->layout_config)->toHaveKey('grid_template_columns');
    expect($layout->layout_config)->toHaveKey('gap');

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/layouts/{$layout->id}", [
            'layout_config' => ['gap' => '2rem'],
        ])
        ->assertOk();

    $layout->refresh();
    expect($layout->layout_config['gap'])->toBe('2rem');
    expect($layout->layout_config)->toHaveKey('grid_template_columns');
    expect($layout->layout_config['grid_template_columns'])->toBe('1fr 1fr');
});

it('accepts full_width on layout_config and strips pre-207 appearance keys', function () {
    // After session 207, layout_config keeps only layout-behavior keys.
    // background_color / padding_* / margin_* moved to appearance_config and
    // are no longer accepted on layout_config.
    $page = apiPage();
    $layout = apiLayout($page);

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/layouts/{$layout->id}", [
            'layout_config' => [
                'full_width'       => true,
                'background_color' => '#ff0000',
                'padding_top'      => '20',
                'margin_left'      => '10',
            ],
        ])
        ->assertOk();

    $layout->refresh();
    expect($layout->layout_config['full_width'])->toBe(true);
    expect($layout->layout_config)->not->toHaveKey('background_color');
    expect($layout->layout_config)->not->toHaveKey('padding_top');
    expect($layout->layout_config)->not->toHaveKey('margin_left');
});

it('accepts appearance_config on layouts and round-trips background + spacing', function () {
    $page = apiPage();
    $layout = apiLayout($page);

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/layouts/{$layout->id}", [
            'appearance_config' => [
                'background' => ['color' => '#ff0000'],
                'layout'     => [
                    'padding' => ['top' => '20', 'left' => '10'],
                    'margin'  => ['bottom' => '15'],
                ],
            ],
        ])
        ->assertOk();

    $layout->refresh();
    expect($layout->appearance_config['background']['color'])->toBe('#ff0000');
    expect($layout->appearance_config['layout']['padding']['top'])->toBe('20');
    expect($layout->appearance_config['layout']['padding']['left'])->toBe('10');
    expect($layout->appearance_config['layout']['margin']['bottom'])->toBe('15');
});

it('strips text and unknown top-level keys from layout appearance_config', function () {
    $page = apiPage();
    $layout = apiLayout($page);

    $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/layouts/{$layout->id}", [
            'appearance_config' => [
                'background' => ['color' => '#abcdef'],
                'text'       => ['color' => '#ffffff'],
                'rogue_key'  => 'drop me',
            ],
        ])
        ->assertOk();

    $layout->refresh();
    expect($layout->appearance_config)->toHaveKey('background');
    expect($layout->appearance_config)->not->toHaveKey('text');
    expect($layout->appearance_config)->not->toHaveKey('rogue_key');
});

it('deletes a layout and cascades to its widgets', function () {
    $page = apiPage();
    $layout = apiLayout($page);
    $child1 = apiChildWidget($page, $layout, 0, 0);
    $child2 = apiChildWidget($page, $layout, 1, 0);

    $response = $this->actingAs(apiUser())
        ->deleteJson(apiPrefix() . "/layouts/{$layout->id}");

    $response->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonStructure(['items', 'required_libs']);

    $this->assertDatabaseMissing('page_layouts', ['id' => $layout->id]);
    $this->assertDatabaseMissing('page_widgets', ['id' => $child1->id]);
    $this->assertDatabaseMissing('page_widgets', ['id' => $child2->id]);
});

it('layout endpoints require update_page permission', function () {
    $page = apiPage();
    $user = apiUser(['view_page']);

    $this->actingAs($user)
        ->postJson(apiPrefix() . "/pages/{$page->id}/layouts", ['label' => 'X'])
        ->assertForbidden();
});

it('returns merged page flow with widgets and layouts interleaved', function () {
    $page = apiPage();

    $w1 = apiWidget($page, 'text_block', 0);
    $layout = apiLayout($page, 1);
    $w2 = apiWidget($page, 'text_block', 2);

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(3);
    expect($items[0]['type'])->toBe('widget');
    expect($items[0]['id'])->toBe($w1->id);
    expect($items[1]['type'])->toBe('layout');
    expect($items[1]['id'])->toBe($layout->id);
    expect($items[2]['type'])->toBe('widget');
    expect($items[2]['id'])->toBe($w2->id);
});

it('reorders widgets and layouts together (mixed reorder)', function () {
    $page = apiPage();
    $w1 = apiWidget($page, 'text_block', 0);
    $layout = apiLayout($page, 1);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/pages/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $layout->id, 'type' => 'layout', 'sort_order' => 0],
                ['id' => $w1->id, 'type' => 'widget', 'layout_id' => null, 'column_index' => null, 'sort_order' => 1],
            ],
        ]);

    $response->assertOk();

    $layout->refresh();
    $w1->refresh();
    expect($layout->sort_order)->toBe(0);
    expect($w1->sort_order)->toBe(1);
});

it('moves a widget into a layout via reorder', function () {
    $page = apiPage();
    $widget = apiWidget($page, 'text_block', 0);
    $layout = apiLayout($page, 1);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/pages/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $widget->id, 'type' => 'widget', 'layout_id' => $layout->id, 'column_index' => 0, 'sort_order' => 0],
            ],
        ]);

    $response->assertOk();

    $widget->refresh();
    expect($widget->layout_id)->toBe($layout->id);
    expect($widget->column_index)->toBe(0);
});

it('moves a widget out of a layout via reorder', function () {
    $page = apiPage();
    $layout = apiLayout($page);
    $widget = apiChildWidget($page, $layout, 0, 0);

    $response = $this->actingAs(apiUser())
        ->putJson(apiPrefix() . "/pages/{$page->id}/widgets/reorder", [
            'items' => [
                ['id' => $widget->id, 'type' => 'widget', 'layout_id' => null, 'column_index' => null, 'sort_order' => 0],
            ],
        ]);

    $response->assertOk();

    $widget->refresh();
    expect($widget->layout_id)->toBeNull();
    expect($widget->column_index)->toBeNull();
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

// ── appearance_image_url in formatWidget ─────────────────────────────────────

it('includes appearance_image_url as null when no image', function () {
    $page = apiPage();
    $widget = apiWidget($page);

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();
    $widgetData = collect($response->json('items'))->firstWhere('id', $widget->id);
    expect($widgetData)->toHaveKey('appearance_image_url');
    expect($widgetData['appearance_image_url'])->toBeNull();
});

it('includes appearance_image_url when image is uploaded', function () {
    $page = apiPage();
    $widget = apiWidget($page);

    \Illuminate\Support\Facades\Storage::fake('public');
    $file = \Illuminate\Http\UploadedFile::fake()->image('bg.jpg', 800, 600);

    $this->actingAs(apiUser())
        ->post(apiPrefix() . "/widgets/{$widget->id}/appearance-image", ['file' => $file])
        ->assertOk();

    $response = $this->actingAs(apiUser())
        ->getJson(apiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();
    $widgetData = collect($response->json('items'))->firstWhere('id', $widget->id);
    expect($widgetData['appearance_image_url'])->not->toBeNull();
});
