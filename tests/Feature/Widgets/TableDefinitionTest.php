<?php

use App\Models\Page;
use App\Models\WidgetType;
use App\Widgets\Table\TableDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Definition / schema shape ───────────────────────────────────────────────

it('exposes the expected schema keys for the table widget', function () {
    $def  = new TableDefinition();
    $keys = collect($def->schema())->pluck('key')->all();

    expect($keys)->toContain('table_html')
        ->toContain('column_widths')
        ->toContain('border')
        ->toContain('header_align')
        ->toContain('header_bg')
        ->toContain('header_text')
        ->toContain('body_align')
        ->toContain('body_bg')
        ->toContain('body_text')
        ->toContain('zebra')
        ->toContain('zebra_bg')
        ->toContain('zebra_text');
});

it('opts the border field into interior gridlines', function () {
    $def    = new TableDefinition();
    $border = collect($def->schema())->firstWhere('key', 'border');

    expect($border['type'])->toBe('border')
        ->and($border['allow_interior'] ?? false)->toBeTrue();
});

it('keeps column_widths as a hidden, data-only field', function () {
    $def    = new TableDefinition();
    $widths = collect($def->schema())->firstWhere('key', 'column_widths');

    expect($widths['inspector'] ?? null)->toBeFalse()
        ->and($def->defaults()['column_widths'])->toBe([]);
});

it('stores the table as a single HTML field, not cell-by-cell', function () {
    $def       = new TableDefinition();
    $tableField = collect($def->schema())->firstWhere('key', 'table_html');

    expect($tableField)->not->toBeNull()
        ->and($tableField['type'])->toBe('table');

    // No repeater / count-picker shape — this is the embedded-editor widget,
    // not the rewound PricingChart-clone.
    $types = collect($def->schema())->pluck('type')->all();
    expect($types)->not->toContain('repeater');
});

it('is not inline-editable — the table is authored in the embedded editor', function () {
    expect((new TableDefinition())->inlineEditable())->toBeFalse();
});

it('ships only SCSS assets — no editor JS reaches the public bundle', function () {
    $assets = (new TableDefinition())->assets();

    expect($assets)->toHaveKey('scss')
        ->and($assets['scss'])->toContain('app/Widgets/Table/styles.scss')
        ->and($assets)->not->toHaveKey('js');
});

it('reveals the zebra colours only when zebra striping is on', function () {
    $def       = new TableDefinition();
    $zebraBg   = collect($def->schema())->firstWhere('key', 'zebra_bg');
    $zebraText = collect($def->schema())->firstWhere('key', 'zebra_text');

    expect($zebraBg['shown_when'] ?? null)->toBe('zebra')
        ->and($zebraText['shown_when'] ?? null)->toBe('zebra');
});

it('defaults round-trip through validate() with a concrete value for every schema key', function () {
    $def = new TableDefinition();
    $def->validate();

    $defaults = $def->defaults();
    foreach ($def->schema() as $field) {
        $key = $field['key'] ?? null;
        if ($key === null) {
            continue;
        }
        expect(array_key_exists($key, $defaults))->toBeTrue("Missing default for [{$key}]");
    }

    expect($defaults['table_html'])->toBe('')
        ->and($defaults['column_widths'])->toBe([])
        ->and($defaults['header_align'])->toBe('center')
        ->and($defaults['body_align'])->toBe('middle-left')
        ->and($defaults['header_bg'])->toBe('#f1f5f9')
        ->and($defaults['body_bg'])->toBe('#ffffff')
        ->and($defaults['zebra'])->toBeFalse()
        ->and($defaults['zebra_bg'])->toBe('#f8fafc')
        ->and($defaults['border'])->toMatchArray(['top' => true, 'inner_horizontal' => true, 'inner_vertical' => true, 'width' => 1, 'color' => '#cbd5e1', 'radius' => 0]);
});

it('seeder creates the table widget type with the correct shape', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'table')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Table')
        ->and($wt->category)->toBe(['content'])
        ->and($wt->render_mode)->toBe('server');

    $assets = is_array($wt->assets) ? $wt->assets : [];
    expect($assets['scss'] ?? [])->toContain('app/Widgets/Table/styles.scss');
});

// ── Render ───────────────────────────────────────────────────────────────────

function makeTableWidget(array $overrides = []): Page
{
    $page = Page::factory()->create(['slug' => 'table-render-test', 'status' => 'published']);
    $wt   = WidgetType::where('handle', 'table')->first();
    $def  = new TableDefinition();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge($def->defaults(), $overrides),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    return $page;
}

it('renders the table HTML inside the np-table wrapper with the border, gridline, colour and alignment styling', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    makeTableWidget([
        'table_html' => '<table><tbody><tr><th><p>Plan</p></th><th><p>Price</p></th></tr>'
            . '<tr><td><p>Starter</p></td><td><p>Free</p></td></tr></tbody></table>',
    ]);

    $response = $this->get('/table-render-test');

    $response->assertOk();
    $response->assertSee('np-table', false);
    $response->assertSee('np-table__scroll', false);
    $response->assertSee('Plan');
    $response->assertSee('Starter');

    // Outer border via composeBorderProps (default: all sides, 1px #cbd5e1).
    $response->assertSee('border-top:1px solid #cbd5e1', false);
    // Interior gridlines are on by default → both modifier classes present.
    $response->assertSee('np-table--inner-h', false);
    $response->assertSee('np-table--inner-v', false);

    // Per-region colour + alignment custom props.
    $response->assertSee('--np-table-header-bg:#f1f5f9', false);
    $response->assertSee('--np-table-header-text:#0f172a', false);
    $response->assertSee('--np-table-body-bg:#ffffff', false);
    $response->assertSee('--np-table-header-align:center', false);
    $response->assertSee('--np-table-body-align:left', false);
});

it('injects a colgroup with percentage widths when column_widths are set', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    makeTableWidget([
        'table_html'    => '<table><tbody><tr><td><p>A</p></td><td><p>B</p></td></tr></tbody></table>',
        'column_widths' => [30, 70],
    ]);

    $response = $this->get('/table-render-test');

    $response->assertOk();
    $response->assertSee('np-table--fixed', false);
    $response->assertSee('<colgroup>', false);
    $response->assertSee('width:30%', false);
    $response->assertSee('width:70%', false);
});

it('emits the zebra class and stripe colours only when zebra is on', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    makeTableWidget([
        'table_html' => '<table><tbody><tr><td><p>A</p></td></tr><tr><td><p>B</p></td></tr></tbody></table>',
        'zebra'      => true,
        'zebra_bg'   => '#eef2ff',
        'zebra_text' => '#312e81',
    ]);

    $response = $this->get('/table-render-test');

    $response->assertOk();
    $response->assertSee('np-table--zebra', false);
    $response->assertSee('--np-table-zebra-bg:#eef2ff', false);
    $response->assertSee('--np-table-zebra-text:#312e81', false);
});

it('preserves colspan / rowspan but strips disallowed markup in the rendered table', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    makeTableWidget([
        'table_html' => '<table><tbody>'
            . '<tr><td colspan="2" rowspan="3" style="color:red" onclick="x()"><p>Spanned</p></td></tr>'
            . '<tr><td><p><script>alert(1)</script>Clean</p></td></tr>'
            . '</tbody></table>',
    ]);

    $response = $this->get('/table-render-test');

    $response->assertOk();
    $response->assertSee('colspan="2"', false);
    $response->assertSee('rowspan="3"', false);
    $response->assertSee('Spanned');
    $response->assertSee('Clean');
    $response->assertDontSee('onclick', false);
    // The script element (and its content) is stripped from the cell — the
    // page chrome legitimately carries its own <script> tags, so assert the
    // smuggled payload is gone rather than the tag in the abstract.
    $response->assertDontSee('alert(1)', false);
    $response->assertDontSee('style="color:red"', false);
});

it('demoConfig provides a populated table for the widget library', function () {
    $def  = new TableDefinition();
    $demo = $def->demoConfig();

    expect($demo['table_html'])->toContain('<table>')
        ->and($demo['table_html'])->toContain('<th>')
        ->and($demo['table_html'])->toContain('Starter');
});
