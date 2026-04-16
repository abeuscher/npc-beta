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
