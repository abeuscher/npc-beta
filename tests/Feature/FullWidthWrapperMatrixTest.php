<?php

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

uses(TestCase::class);

function renderBlade(array $block): string
{
    return Blade::render(
        '<x-page-widgets :blocks="$blocks" />',
        ['blocks' => [$block]],
    );
}

it('emits .widget with inner .site-container for (bg:true, content:false)', function () {
    $html = renderBlade([
        'handle'                => 'text_block',
        'instance_id'           => 'wb-1',
        'html'                  => '<p>hello</p>',
        'inline_style'          => 'background-color:#ff0000',
        'background_full_width' => true,
        'content_full_width'    => false,
    ]);

    expect($html)
        ->toContain('class="widget widget--text_block"')
        ->toContain('id="widget-wb-1"')
        ->toContain('style="background-color:#ff0000"')
        ->toContain('<div class="site-container">')
        ->toContain('<p>hello</p>');

    // No outer .site-container wrap (background extends edge-to-edge).
    expect(preg_match_all('/<div class="site-container">/', $html))->toBe(1);
});

it('emits no site-container wrappers for (bg:true, content:true)', function () {
    $html = renderBlade([
        'handle'                => 'nav',
        'instance_id'           => 'wb-2',
        'html'                  => '<nav>links</nav>',
        'inline_style'          => '',
        'background_full_width' => true,
        'content_full_width'    => true,
    ]);

    expect($html)
        ->toContain('class="widget widget--nav"')
        ->toContain('<nav>links</nav>')
        ->not->toContain('site-container');
});

it('emits outer + inner .site-container for (bg:false, content:false)', function () {
    $html = renderBlade([
        'handle'                => 'text_block',
        'instance_id'           => 'wb-3',
        'html'                  => '<p>boxed</p>',
        'inline_style'          => '',
        'background_full_width' => false,
        'content_full_width'    => false,
    ]);

    expect(preg_match_all('/<div class="site-container">/', $html))->toBe(2);
    expect($html)->toContain('<p>boxed</p>');
});

it('normalizes (bg:false, content:true) to (bg:true, content:true) — no wrappers', function () {
    $html = renderBlade([
        'handle'                => 'text_block',
        'instance_id'           => 'wb-4',
        'html'                  => '<p>bare</p>',
        'inline_style'          => '',
        'background_full_width' => false,
        'content_full_width'    => true,
    ]);

    expect($html)
        ->not->toContain('site-container')
        ->toContain('<p>bare</p>');
});

it('skips inner content wrap for layout blocks (handle === page_layout) regardless of content flag', function () {
    // Layout blocks self-wrap content inside .page-layout via PageBlockRenderer;
    // the blade must not add a second inner .site-container.
    $html = renderBlade([
        'handle'                => 'page_layout',
        'instance_id'           => 'wb-5',
        'html'                  => '<div class="page-layout"><div class="site-container"><div class="layout-grid"></div></div></div>',
        'inline_style'          => '',
        'background_full_width' => true,
        'content_full_width'    => false,
    ]);

    // Only the .site-container baked into the layout html (one occurrence) — no
    // second wrapper added by the blade.
    expect(preg_match_all('/<div class="site-container">/', $html))->toBe(1);
});
