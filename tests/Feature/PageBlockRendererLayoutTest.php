<?php

use App\Models\Page;
use App\Models\PageLayout;
use App\Services\PageBlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('design');

beforeEach(function () {
    $this->renderer = app(PageBlockRenderer::class);
    $this->page = Page::factory()->create([
        'slug'   => 'rlt-' . uniqid(),
        'status' => 'published',
    ]);
});

function makePageLayout(Page $page, array $layoutConfig = []): PageLayout
{
    return $page->layouts()->create([
        'label'         => 'Layout',
        'display'       => 'grid',
        'columns'       => 1,
        'layout_config' => array_merge(['grid_template_columns' => '1fr'], $layoutConfig),
        'sort_order'    => 0,
    ]);
}

it('emits .page-layout > .site-container > .layout-grid for (bg:true, content:false)', function () {
    $layout = makePageLayout($this->page, [
        'background_full_width' => true,
        'content_full_width'    => false,
    ]);

    $styles = '';
    $scripts = '';
    $assets = [];
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts, $assets);

    expect($block['handle'])->toBe('page_layout');
    expect($block['background_full_width'])->toBeTrue();
    expect($block['content_full_width'])->toBeFalse();
    expect($block['html'])
        ->toMatch('#<div class="page-layout"[^>]*>\s*<div class="site-container">\s*<div class="layout-grid"#');
});

it('emits .page-layout > .layout-grid (no site-container) for (bg:true, content:true)', function () {
    $layout = makePageLayout($this->page, [
        'background_full_width' => true,
        'content_full_width'    => true,
    ]);

    $styles = '';
    $scripts = '';
    $assets = [];
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts, $assets);

    expect($block['html'])
        ->toMatch('#<div class="page-layout"[^>]*>\s*<div class="layout-grid"#')
        ->not->toContain('site-container');
});

it('separates appearance from grid display: .page-layout carries bg, .layout-grid carries display:grid', function () {
    $layout = $this->page->layouts()->create([
        'label'             => 'Layout',
        'display'           => 'grid',
        'columns'           => 2,
        'layout_config'     => [
            'grid_template_columns' => '1fr 2fr',
            'background_full_width' => true,
            'content_full_width'    => true,
        ],
        'appearance_config' => [
            'background' => ['color' => '#abcdef'],
        ],
        'sort_order'        => 0,
    ]);

    $styles = '';
    $scripts = '';
    $assets = [];
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts, $assets);

    expect($block['html'])->toContain('background-color:#abcdef');
    expect($block['html'])->toMatch('~<div class="page-layout"[^>]*style="[^"]*background-color:[^"]*"~');
    // The column track is emitted as the single --layout-cols custom property
    // (session 294), not an inline grid-template-columns; the stylesheet owns
    // grid-template-columns so the container-query collapse needs no !important.
    expect($block['html'])->toMatch('~<div class="layout-grid" data-collapse-mobile="(?:true|false)" style="[^"]*display:grid;--layout-cols:1fr 2fr~');
});

it('emits the column track as --layout-cols and a default-on data-collapse-mobile (concrete-value)', function () {
    // collapse_mobile key absent → resolves to a concrete true at read, never
    // branches on "missing". No backfill migration; default-on server-side.
    $layout = makePageLayout($this->page, ['grid_template_columns' => '2fr 3fr']);

    $styles = '';
    $scripts = '';
    $assets = [];
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts, $assets);

    expect($block['html'])
        ->toContain('--layout-cols:2fr 3fr')
        ->toContain('data-collapse-mobile="true"')
        ->not->toContain('grid-template-columns:');
});

it('emits data-collapse-mobile="false" when the layout opts out', function () {
    $layout = makePageLayout($this->page, [
        'grid_template_columns' => '1fr 1fr',
        'collapse_mobile'       => false,
    ]);

    $styles = '';
    $scripts = '';
    $assets = [];
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts, $assets);

    expect($block['html'])->toContain('data-collapse-mobile="false"');
});

it('clamps content_full_width=true to (bg:true) when bg is false (normalization)', function () {
    $layout = makePageLayout($this->page, [
        'background_full_width' => false,
        'content_full_width'    => true,
    ]);

    $styles = '';
    $scripts = '';
    $assets = [];
    $block = $this->renderer->renderLayoutBlock($layout, $styles, $scripts, $assets);

    expect($block['background_full_width'])->toBeTrue();
    expect($block['content_full_width'])->toBeTrue();
});
