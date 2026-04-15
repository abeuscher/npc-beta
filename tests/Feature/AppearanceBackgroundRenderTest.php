<?php

use App\Models\Event;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\AppearanceStyleComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->composer = new AppearanceStyleComposer();

    $this->page = Page::factory()->create([
        'title'  => 'Render Test Page',
        'slug'   => 'render-test-' . uniqid(),
        'status' => 'published',
    ]);

    $this->widgetType = WidgetType::create([
        'handle'        => 'render_test_' . uniqid(),
        'label'         => 'Render Test',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => [],
        'full_width'    => false,
    ]);
});

function renderWidget(Page $page, WidgetType $wt, array $ac = [], ?string $layoutId = null): PageWidget
{
    return PageWidget::create([
        'page_id'           => $page->id,
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

it('renders color only without background-image props', function () {
    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => ['color' => '#ff0000'],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->toContain('background-color:#ff0000')
        ->not->toContain('background-image')
        ->not->toContain('background-position')
        ->not->toContain('background-size');
});

it('renders color + opaque gradient', function () {
    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => [
            'color' => '#ffffff',
            'gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#000000', 'to' => '#ffffff', 'angle' => 45],
                ],
            ],
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->toContain('background-color:#ffffff')
        ->toContain('background-image:linear-gradient(45deg, #000000, #ffffff)');
});

it('renders color + image', function () {
    Storage::fake('public');

    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => ['color' => '#cccccc'],
    ]);

    $file = UploadedFile::fake()->image('bg.jpg', 800, 600);
    $pw->addMedia($file)
        ->usingFileName('bg.jpg')
        ->toMediaCollection('appearance_background_image', 'public');

    $pw->refresh();
    $result = $this->composer->compose($pw);

    expect($result['inline_style'])
        ->toContain('background-color:#cccccc')
        ->toContain('background-image:url(')
        ->toContain('background-position:50% 50%')
        ->toContain('background-size:cover');
});

it('renders color + image + alpha gradient (tint case)', function () {
    Storage::fake('public');

    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => [
            'color' => '#000000',
            'gradient' => [
                'gradients' => [
                    ['type' => 'linear', 'from' => '#000000', 'from_alpha' => 50, 'to' => '#000000', 'to_alpha' => 50],
                ],
            ],
        ],
    ]);

    $file = UploadedFile::fake()->image('hero.jpg', 1200, 800);
    $pw->addMedia($file)
        ->usingFileName('hero.jpg')
        ->toMediaCollection('appearance_background_image', 'public');

    $pw->refresh();
    $result = $this->composer->compose($pw);

    // Both gradient (with rgba) and image url should appear, gradient first
    expect($result['inline_style'])
        ->toContain('url(')
        ->toContain('rgba(');

    // Verify comma order: gradient before url
    $bgImageMatch = [];
    preg_match('/background-image:(.+?)(?:;|$)/', $result['inline_style'], $bgImageMatch);
    $bgImageValue = $bgImageMatch[1] ?? '';
    $gradientPos = strpos($bgImageValue, 'rgba(');
    $urlPos = strpos($bgImageValue, 'url(');
    expect($gradientPos)->toBeLessThan($urlPos);
});

it('renders each fit value', function (string $fit) {
    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
            'fit' => $fit,
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-size:' . $fit);
})->with(['cover', 'contain']);

it('renders each alignment value', function (string $alignment, string $position) {
    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => [
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
            'alignment' => $alignment,
        ],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-position:' . $position);
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

it('resolves full_width true at root', function () {
    $pw = renderWidget($this->page, $this->widgetType, [
        'layout' => ['full_width' => true],
    ]);
    $result = $this->composer->compose($pw);
    expect($result['is_full_width'])->toBeTrue();
});

it('forces full_width false for column-child widget', function () {
    $layout = PageLayout::create([
        'page_id'       => $this->page->id,
        'label'         => 'Test Layout',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => [],
        'sort_order'    => 0,
    ]);

    $pw = renderWidget($this->page, $this->widgetType, [
        'layout' => ['full_width' => true],
    ], $layout->id);

    $result = $this->composer->compose($pw);
    expect($result['is_full_width'])->toBeFalse();
});

// ── use_current_page_header override ────────────────────────────────────────

it('uses post_header from the current page when override is on', function () {
    Storage::fake('public');

    $this->page->addMedia(UploadedFile::fake()->image('post-hdr.jpg', 1200, 400))
        ->usingFileName('post-hdr.jpg')
        ->toMediaCollection('post_header', 'public');

    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => ['use_current_page_header' => true],
    ]);

    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-image:url(');
});

it('uses event_header when override is on and page is an event landing page', function () {
    Storage::fake('public');

    $this->page->update(['type' => 'event']);

    $event = Event::factory()->create([
        'title'           => 'Gala',
        'landing_page_id' => $this->page->id,
    ]);
    $event->addMedia(UploadedFile::fake()->image('event-hdr.jpg', 1200, 400))
        ->usingFileName('event-hdr.jpg')
        ->toMediaCollection('event_header', 'public');

    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => ['use_current_page_header' => true],
    ]);

    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-image:url(');
});

it('renders no background image when override is on and no header is attached', function () {
    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => ['use_current_page_header' => true],
    ]);

    $result = $this->composer->compose($pw);
    expect($result['inline_style'])
        ->not->toContain('background-image')
        ->not->toContain('background-position')
        ->not->toContain('background-size');
});

it('ignores the widget-owned background image when override is on', function () {
    Storage::fake('public');

    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => ['use_current_page_header' => true],
    ]);

    $pw->addMedia(UploadedFile::fake()->image('widget-bg.jpg', 800, 600))
        ->usingFileName('widget-bg.jpg')
        ->toMediaCollection('appearance_background_image', 'public');
    $pw->refresh();

    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->not->toContain('background-image');
});

it('still composes gradient layer when override is on and no header is attached', function () {
    $pw = renderWidget($this->page, $this->widgetType, [
        'background' => [
            'use_current_page_header' => true,
            'gradient' => ['gradients' => [['type' => 'linear', 'from' => '#000', 'to' => '#fff']]],
        ],
    ]);

    $result = $this->composer->compose($pw);
    expect($result['inline_style'])->toContain('background-image:linear-gradient(');
});
