<?php

use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\AppearanceStyleComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->composer = new AppearanceStyleComposer();

    $this->page = Page::factory()->create([
        'title'  => 'Composer Test Page',
        'slug'   => 'composer-test-' . uniqid(),
        'status' => 'published',
    ]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'test_widget_' . uniqid(),
        'label'         => 'Test Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'full_width'    => false,
    ]);
});

function makeWidget(Page $page, WidgetType $wt, array $ac = [], ?string $layoutId = null): PageWidget
{
    return $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'layout_id'         => $layoutId,
        'label'             => 'Test',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => $ac,
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

// ── Empty / minimal ─────────────────────────────────────────────────────────

it('returns empty style for empty appearance_config', function () {
    $pw = makeWidget($this->page, $this->widgetType, []);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toBe('');
    expect($result['is_full_width'])->toBeFalse();
});

// ── Color ───────────────────────────────────────────────────────────────────

it('emits background-color for valid hex', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => ['color' => '#ff0000'],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-color:#ff0000');
});

it('rejects malformed color', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => ['color' => 'red'],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->not->toContain('background-color');
});

it('emits text color', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'text' => ['color' => '#333'],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('color:#333');
});

// ── Spacing ─────────────────────────────────────────────────────────────────

it('emits padding and margin', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'layout' => [
            'padding' => ['top' => 10, 'bottom' => 20],
            'margin'  => ['left' => 5],
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->toContain('padding-top:10px')
        ->toContain('padding-bottom:20px')
        ->toContain('margin-left:5px');
});

it('casts spacing to int', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'layout' => ['padding' => ['top' => '15.7']],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('padding-top:15px');
});

// ── Full width ──────────────────────────────────────────────────────────────

it('resolves full_width true from appearance_config', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'layout' => ['full_width' => true],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['is_full_width'])->toBeTrue();
});

it('falls back to widget_type default for full_width', function () {
    $wt = WidgetType::create([
        'handle'        => 'fw_widget_' . uniqid(),
        'label'         => 'Full Width Widget',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'full_width'    => true,
    ]);
    $pw = makeWidget($this->page, $wt, []);
    $result = $this->composer->compose($pw);
    expect($result['is_full_width'])->toBeTrue();
});

it('forces full_width false for column-child widget', function () {
    $layout = $this->page->layouts()->create([
        'label'         => 'Test Layout',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [],
        'sort_order'    => 0,
    ]);

    $pw = makeWidget($this->page, $this->widgetType, [
        'layout' => ['full_width' => true],
    ], $layout->id);

    $result = $this->composer->compose($pw);
    expect($result['is_full_width'])->toBeFalse();
});

it('treats malformed full_width as fallback to widget_type default', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'layout' => ['full_width' => null],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['is_full_width'])->toBe($this->widgetType->full_width);
});

// ── Gradient only ───────────────────────────────────────────────────────────

it('emits gradient-only background-image', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#000000', 'to' => '#ffffff', 'angle' => 90],
                ],
            ],
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->toContain('background-image:linear-gradient(90deg, #000000, #ffffff)')
        ->toContain('background-position:50% 50%')
        ->toContain('background-size:cover');
});

it('emits gradient with alpha using rgba', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#000000', 'from_alpha' => 50, 'to' => '#ffffff', 'to_alpha' => 0, 'angle' => 180],
                ],
            ],
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->toContain('rgba(0, 0, 0, 0.5)')
        ->toContain('rgba(255, 255, 255, 0)');
});

// ── Alignment ───────────────────────────────────────────────────────────────

it('maps alignment values to CSS background-position', function (string $alignment, string $expected) {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
            'alignment' => $alignment,
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-position:' . $expected);
})->with([
    ['top-left',      '0% 0%'],
    ['top-center',    '50% 0%'],
    ['top-right',     '100% 0%'],
    ['middle-left',   '0% 50%'],
    ['center',        '50% 50%'],
    ['middle-right',  '100% 50%'],
    ['bottom-left',   '0% 100%'],
    ['bottom-center', '50% 100%'],
    ['bottom-right',  '100% 100%'],
]);

it('defaults malformed alignment to center', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
            'alignment' => 'bogus',
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-position:50% 50%');
});

// ── Fit ─────────────────────────────────────────────────────────────────────

it('emits cover and contain fit values', function (string $fit) {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
            'fit' => $fit,
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-size:' . $fit);
})->with(['cover', 'contain']);

it('defaults malformed fit to cover', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
            'fit' => 'fill',
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-size:cover');
});

// ── Combined ────────────────────────────────────────────────────────────────

it('composes color + gradient combined', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => [
            'color' => '#aabbcc',
            'gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#000000', 'to' => '#ffffff'],
                ],
            ],
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->toContain('background-color:#aabbcc')
        ->toContain('background-image:linear-gradient(');
});

// ── No background-image when neither gradient nor image ─────────────────────

it('skips background-image props when no gradient and no image', function () {
    $pw = makeWidget($this->page, $this->widgetType, [
        'background' => ['color' => '#ffffff'],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->not->toContain('background-image')
        ->not->toContain('background-position')
        ->not->toContain('background-size');
});

// ── composeForLayout (session 207) ──────────────────────────────────────────

function makeLayout(Page $page, array $ac = []): PageLayout
{
    return $page->layouts()->create([
        'label'             => 'Test Layout',
        'display'           => 'grid',
        'columns'           => 2,
        'layout_config'     => [],
        'appearance_config' => $ac,
        'sort_order'        => 0,
    ]);
}

it('returns empty style for an empty layout appearance_config', function () {
    $layout = makeLayout($this->page, []);
    expect($this->composer->composeForLayout($layout))->toBe('');
});

it('emits background-color on a layout for a valid hex', function () {
    $layout = makeLayout($this->page, [
        'background' => ['color' => '#ff0000'],
    ]);
    expect($this->composer->composeForLayout($layout))->toContain('background-color:#ff0000');
});

it('rejects invalid hex for layout background-color', function () {
    $layout = makeLayout($this->page, [
        'background' => ['color' => 'notahex'],
    ]);
    expect($this->composer->composeForLayout($layout))->not->toContain('background-color');
});

it('emits layout padding and margin per side for present non-zero values', function () {
    $layout = makeLayout($this->page, [
        'layout' => [
            'padding' => ['top' => '12', 'left' => '24', 'right' => '24', 'bottom' => '12'],
            'margin'  => ['top' => '8'],
        ],
    ]);
    $style = $this->composer->composeForLayout($layout);
    expect($style)
        ->toContain('padding-top:12px')
        ->toContain('padding-right:24px')
        ->toContain('padding-bottom:12px')
        ->toContain('padding-left:24px')
        ->toContain('margin-top:8px')
        ->not->toContain('margin-right')
        ->not->toContain('margin-bottom')
        ->not->toContain('margin-left');
});

it('emits gradient with position, size, repeat on a layout', function () {
    $layout = makeLayout($this->page, [
        'background' => [
            'gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#000000', 'to' => '#ffffff'],
                ],
            ],
            'alignment' => 'top-left',
            'fit'       => 'contain',
        ],
    ]);
    $style = $this->composer->composeForLayout($layout);
    expect($style)
        ->toContain('background-image:linear-gradient(')
        ->toContain('background-position:0% 0%')
        ->toContain('background-size:contain')
        ->toContain('background-repeat:no-repeat');
});

it('does not emit text color, text shadow, or full_width on layouts', function () {
    // These keys either belong to widgets only (text) or to layout_config (full_width).
    // Passing them via appearance_config should have no effect on composeForLayout output.
    $layout = makeLayout($this->page, [
        'text'   => ['color' => '#00ff00', 'shadow' => true],
        'layout' => ['full_width' => true],
    ]);
    $style = $this->composer->composeForLayout($layout);
    expect($style)
        ->not->toContain('color:')
        ->not->toContain('text-shadow');
});

// ── Migration round-trip (session 207) ─────────────────────────────────────

it('produces byte-equivalent CSS for pre-207 layout_config after data migration', function () {
    // Pre-207, the renderer emitted layout container CSS from layout_config keys
    // (background_color, padding_*, margin_*). Post-207, those values live on
    // appearance_config under the new nested shape and composeForLayout
    // produces the equivalent CSS. This locks in round-trip equivalence for
    // the data migration.
    $preMigration = [
        'background_color' => '#ff0000',
        'padding_top'      => '20',
        'padding_right'    => '10',
        'padding_bottom'   => '15',
        'padding_left'     => '5',
        'margin_top'       => '8',
        'margin_bottom'    => '4',
    ];

    // Reproduce the pre-207 renderer logic.
    $preStyle = '';
    $spacingKeys = [
        'padding_top' => 'padding-top', 'padding_right' => 'padding-right',
        'padding_bottom' => 'padding-bottom', 'padding_left' => 'padding-left',
        'margin_top' => 'margin-top', 'margin_right' => 'margin-right',
        'margin_bottom' => 'margin-bottom', 'margin_left' => 'margin-left',
    ];
    foreach ($spacingKeys as $key => $cssProp) {
        $val = isset($preMigration[$key]) && $preMigration[$key] !== '' ? (int) $preMigration[$key] : null;
        if ($val !== null) {
            $preStyle .= $cssProp . ':' . $val . 'px;';
        }
    }
    if (! empty($preMigration['background_color'])) {
        $preStyle .= 'background-color:' . $preMigration['background_color'] . ';';
    }

    // Simulate the 207 data migration.
    $postMigration = [
        'background' => ['color' => $preMigration['background_color']],
        'layout'     => [
            'padding' => [
                'top'    => $preMigration['padding_top'],
                'right'  => $preMigration['padding_right'],
                'bottom' => $preMigration['padding_bottom'],
                'left'   => $preMigration['padding_left'],
            ],
            'margin' => [
                'top'    => $preMigration['margin_top'],
                'bottom' => $preMigration['margin_bottom'],
            ],
        ],
    ];
    $layout = makeLayout($this->page, $postMigration);
    $postStyle = $this->composer->composeForLayout($layout);

    // Normalize both sides to a sorted set of "prop:value" decls so we compare
    // semantic equivalence, not declaration order.
    $normalize = function (string $style): array {
        $decls = array_filter(array_map('trim', explode(';', $style)));
        sort($decls);
        return $decls;
    };

    expect($normalize($postStyle))->toEqual($normalize($preStyle));
});
