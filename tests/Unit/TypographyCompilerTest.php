<?php

use App\Services\TypographyCompiler;
use App\Services\TypographyResolver;

it('emits concrete declarations from the defaults tree', function () {
    $css = TypographyCompiler::compile(TypographyResolver::defaults());

    expect($css)->toContain('h1:not(nav h1) {');
    expect($css)->toContain("font-family: 'Inter', system-ui, sans-serif");
    expect($css)->toContain('font-weight: 700');
    expect($css)->toContain('font-size: 2.5rem');
    expect($css)->toContain('ul:not(nav ul) li {');
    expect($css)->toContain('list-style-type: disc');
    expect($css)->toContain('ol:not(nav ol) li {');
    expect($css)->toContain('list-style-type: decimal');
});

it('emits declarations from a configured element', function () {
    $state = TypographyResolver::defaults();
    $state['elements']['h1']['font']['family'] = "'Inter', sans-serif";
    $state['elements']['h1']['font']['weight'] = '700';
    $state['elements']['h1']['font']['size']   = ['value' => 2.5, 'unit' => 'rem'];
    $state['elements']['h1']['font']['line_height'] = 1.2;
    $state['elements']['h1']['font']['letter_spacing'] = ['value' => -0.02, 'unit' => 'em'];
    $state['elements']['h1']['font']['case'] = 'uppercase';
    $state['elements']['h1']['margin']['top'] = 24;
    $state['elements']['h1']['padding']['left'] = 8;

    $css = TypographyCompiler::compile($state);

    expect($css)->toContain('h1:not(nav h1) {');
    expect($css)->toContain("font-family: 'Inter', sans-serif");
    expect($css)->toContain('font-weight: 700');
    expect($css)->toContain('font-size: 2.5rem');
    expect($css)->toContain('line-height: 1.2');
    expect($css)->toContain('letter-spacing: -0.02em');
    expect($css)->toContain('text-transform: uppercase');
    expect($css)->toContain('margin-top: 24px');
    expect($css)->toContain('padding-left: 8px');
});

it('emits list-style-type for ul_li and ol_li', function () {
    $state = TypographyResolver::defaults();
    $state['elements']['ul_li']['list_style_type'] = 'square';
    $state['elements']['ol_li']['list_style_type'] = 'lower-roman';
    $state['elements']['ul_li']['marker_color']    = '#ff0000';

    $css = TypographyCompiler::compile($state);

    expect($css)->toContain('ul:not(nav ul) li {');
    expect($css)->toContain('list-style-type: square');
    expect($css)->toContain('--np-list-marker-color: #ff0000');
    expect($css)->toContain('ol:not(nav ol) li {');
    expect($css)->toContain('list-style-type: lower-roman');
});

it('detects Google Fonts families in buckets and elements', function () {
    $state = TypographyResolver::defaults();
    $state['buckets']['heading_family'] = "'Inter', sans-serif";
    $state['elements']['p']['font']['family']    = "'Lato', sans-serif";
    $state['elements']['h1']['font']['family']   = "'Inter', sans-serif";

    $fonts = TypographyCompiler::googleFontsUsed($state);

    expect($fonts)->toContain('Inter');
    expect($fonts)->toContain('Lato');
});
