<?php

use App\Models\Page;
use App\Models\WidgetType;
use App\Widgets\PricingChart\PricingChartDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Definition / schema shape ───────────────────────────────────────────────

it('exposes the expected schema keys for the pricing_chart widget', function () {
    $def = new PricingChartDefinition();
    $keys = collect($def->schema())->pluck('key')->all();

    expect($keys)->toContain('eyebrow_label')
        ->toContain('heading')
        ->toContain('subheading')
        ->toContain('columns')
        ->toContain('footnote')
        ->toContain('heading_alignment')
        ->toContain('gap');
});

it('defines columns as a repeater with the expected nested fields', function () {
    $def = new PricingChartDefinition();
    $columnsField = collect($def->schema())->firstWhere('key', 'columns');

    expect($columnsField)->not->toBeNull()
        ->and($columnsField['type'])->toBe('repeater')
        ->and($columnsField['fields'])->toBeArray();

    $nestedKeys = collect($columnsField['fields'])->pluck('key')->all();
    expect($nestedKeys)->toContain('emphasize')
        ->toContain('eyebrow')
        ->toContain('title')
        ->toContain('price')
        ->toContain('lead_content')
        ->toContain('attribute_rows')
        ->toContain('ctas');

    $attrRows = collect($columnsField['fields'])->firstWhere('key', 'attribute_rows');
    expect($attrRows['type'])->toBe('repeater');
    $attrKeys = collect($attrRows['fields'])->pluck('key')->all();
    expect($attrKeys)->toContain('label')->toContain('value');

    $ctas = collect($columnsField['fields'])->firstWhere('key', 'ctas');
    expect($ctas['type'])->toBe('buttons');
});

it('defaults round-trip through validate() with a concrete value for every schema key', function () {
    $def = new PricingChartDefinition();
    $def->validate();

    $defaults = $def->defaults();
    foreach ($def->schema() as $field) {
        $key = $field['key'] ?? null;
        if ($key === null) {
            continue;
        }
        expect(array_key_exists($key, $defaults))->toBeTrue("Missing default for [{$key}]");
    }

    expect($defaults['columns'])->toBe([])
        ->and($defaults['heading_alignment'])->toBe('center')
        ->and($defaults['eyebrow_label'])->toBe('')
        ->and($defaults['footnote'])->toBe('');
});

it('seeder creates pricing_chart widget type with correct shape', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'pricing_chart')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Pricing Chart')
        ->and($wt->category)->toBe(['content'])
        ->and($wt->collections)->toBe([])
        ->and($wt->render_mode)->toBe('server');

    $assets = is_array($wt->assets) ? $wt->assets : [];
    expect($assets['scss'] ?? [])->toContain('app/Widgets/PricingChart/styles.scss');
});

it('demoConfig provides three columns with one emphasized', function () {
    $def = new PricingChartDefinition();
    $demo = $def->demoConfig();

    expect($demo['columns'])->toBeArray()->toHaveCount(3);

    $emphasized = collect($demo['columns'])->where('emphasize', true);
    expect($emphasized)->toHaveCount(1);
    expect($emphasized->first()['title'])->toBe('Monthly');
});

// ── Render ─────────────────────────────────────────────────────────────────

it('renders three columns with titles, prices, attribute rows and CTAs', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $page = Page::factory()->create(['slug' => 'pricing-chart-render-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'pricing_chart')->first();

    $def = new PricingChartDefinition();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge($def->defaults(), [
            'eyebrow_label' => 'PRICING',
            'heading'       => 'Three ways to try this.',
            'subheading'    => '<p>Pick the one that fits where you are.</p>',
            'columns'       => $def->marketingSiteTiers(),
            'footnote'      => '<p><em>* fine print</em></p>',
        ]),
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/pricing-chart-render-test');

    $response->assertOk();
    $response->assertSee('Three ways to try this.');
    $response->assertSee('Instant Demo');
    $response->assertSee('7-Day Trial');
    $response->assertSee('Monthly');
    $response->assertSee('Recommended');
    $response->assertSee('Try the demo');
    $response->assertSee('Request a trial');
    $response->assertSee('Get started');
    $response->assertSee('widget-pricing-chart', false);
    $response->assertSee('pricing-chart__column--emphasized', false);
    $response->assertSee('pricing-chart__attribute-row', false);
    $response->assertSee('fine print');
});

it('renders no columns container when columns array is empty', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $page = Page::factory()->create(['slug' => 'pricing-chart-empty-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'pricing_chart')->first();

    $def = new PricingChartDefinition();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge($def->defaults(), [
            'heading' => 'Heading only — no columns yet',
        ]),
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/pricing-chart-empty-test');

    $response->assertOk();
    $response->assertSee('Heading only — no columns yet');
    $response->assertDontSee('pricing-chart__columns', false);
});

it('pads attribute rows so subgrid alignment works across cards with different row counts', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $page = Page::factory()->create(['slug' => 'pricing-chart-uneven-test', 'status' => 'published']);
    $wt = WidgetType::where('handle', 'pricing_chart')->first();

    $def = new PricingChartDefinition();

    $page->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge($def->defaults(), [
            'columns' => [
                [
                    'emphasize' => false, 'eyebrow' => '', 'title' => 'Short',
                    'price' => '<p>Free</p>', 'lead_content' => '',
                    'attribute_rows' => [
                        ['label' => 'A', 'value' => '<p>1</p>'],
                    ],
                    'ctas' => [],
                ],
                [
                    'emphasize' => true, 'eyebrow' => 'Top', 'title' => 'Long',
                    'price' => '<p>$10</p>', 'lead_content' => '',
                    'attribute_rows' => [
                        ['label' => 'A', 'value' => '<p>1</p>'],
                        ['label' => 'B', 'value' => '<p>2</p>'],
                        ['label' => 'C', 'value' => '<p>3</p>'],
                    ],
                    'ctas' => [],
                ],
            ],
        ]),
        'sort_order' => 0,
        'is_active'  => true,
    ]);

    $response = $this->get('/pricing-chart-uneven-test');

    $response->assertOk();
    // The shorter card pads two empty rows so subgrid placement matches.
    $response->assertSee('pricing-chart__attribute-row--empty', false);
    // Root style carries the column count and max attribute row count for the
    // CSS variables driving the parent grid.
    $response->assertSee('--pc-columns: 2', false);
    $response->assertSee('--pc-attr-rows: 3', false);
});
