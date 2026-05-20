<?php

use App\Services\TypographyCompiler;
use App\Services\TypographyResolver;

uses()->group('design');

it('emits concrete declarations from the defaults tree', function () {
    $css = TypographyCompiler::compile(TypographyResolver::defaults());

    expect($css)->toContain('h1:not(nav h1) {');
    expect($css)->toContain("font-family: 'Inter', system-ui, sans-serif");
    expect($css)->toContain('font-weight: 700');
    expect($css)->toContain('font-size: 2.5rem');
    // Sane baseline vertical rhythm ships from code defaults (the
    // zero-margin product-defect guard).
    expect($css)->toContain('h1:not(nav h1) { ');
    expect($css)->toMatch('/h1:not\(nav h1\) \{[^}]*margin-bottom: 1\.5rem/');
    expect($css)->toMatch('/p:not\(nav p\) \{[^}]*margin-bottom: 1rem/');
    expect($css)->toContain('ul:not(nav ul) li:not([data-list]) {');
    expect($css)->toContain('list-style-type: disc');
    expect($css)->toContain('ol:not(nav ol) li:not([data-list]) {');
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
    // Pin the box unit to px — this case asserts integer-px margin/padding
    // emission, independent of the rem default rhythm.
    $state['elements']['h1']['margin']['unit'] = 'px';
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

    expect($css)->toContain('ul:not(nav ul) li:not([data-list]) {');
    expect($css)->toContain('list-style-type: square');
    expect($css)->toContain('--np-list-marker-color: #ff0000');
    expect($css)->toContain('ol:not(nav ol) li:not([data-list]) {');
    expect($css)->toContain('list-style-type: lower-roman');
});

it('emits the xl declaration plus three @media breakpoint blocks per element', function () {
    $css = TypographyCompiler::compile(TypographyResolver::defaults());

    // h1 default 2.5rem → display ramp (lg .85, md .75, sm .60).
    expect($css)->toContain('h1:not(nav h1) { ');
    expect($css)->toContain('font-size: 2.5rem');
    expect($css)->toContain('@media (max-width: 992px) { h1:not(nav h1) { font-size: 2.125rem; } }');
    expect($css)->toContain('@media (max-width: 768px) { h1:not(nav h1) { font-size: 1.875rem; } }');
    expect($css)->toContain('@media (max-width: 576px) { h1:not(nav h1) { font-size: 1.5rem; } }');

    // Body class (p) does not scale — the three blocks re-state the xl size.
    expect($css)->toContain('@media (max-width: 992px) { p:not(nav p) { font-size: 1rem; } }');
    expect($css)->toContain('@media (max-width: 576px) { p:not(nav p) { font-size: 1rem; } }');
});

it('keeps line-height unitless and only in the base block (it rides the size)', function () {
    $css   = TypographyCompiler::compile(TypographyResolver::defaults());
    $lines = explode("\n", $css);

    $base = collect($lines)->first(fn ($l) => str_starts_with($l, 'h1:not(nav h1) { '));
    expect($base)->toContain('line-height: 1.2');

    // line-height appears in the base block only — never inside an @media block.
    foreach ($lines as $line) {
        if (str_starts_with($line, '@media')) {
            expect($line)->not->toContain('line-height');
        }
    }
});

it('honours the stored margin/padding unit and never int-truncates (the rem→1px bug)', function () {
    $state = TypographyResolver::defaults();
    // This install's real shape: a deliberate rem rhythm with a per-box unit.
    $state['elements']['h1']['margin'] = ['top' => 0, 'right' => 0, 'bottom' => 1.5,  'left' => 0, 'unit' => 'rem'];
    $state['elements']['h3']['margin'] = ['top' => 0, 'right' => 0, 'bottom' => 1,    'left' => 0, 'unit' => 'rem'];
    $state['elements']['p']['margin']  = ['top' => 0, 'right' => 0, 'bottom' => 0.75, 'left' => 0, 'unit' => 'rem'];

    $css   = TypographyCompiler::compile($state);
    $block = fn ($sel) => collect(explode("\n", $css))->first(fn ($l) => str_starts_with($l, $sel . ' { '));

    // Fractional rem survives verbatim — NOT (int)1.5 . 'px' = "1px".
    expect($block('h1:not(nav h1)'))->toContain('margin-bottom: 1.5rem');
    expect($block('h3:not(nav h3)'))->toContain('margin-bottom: 1rem');
    expect($block('p:not(nav p)'))->toContain('margin-bottom: 0.75rem');
    expect($css)->not->toContain('margin-bottom: 1px');

    // A box with no unit key → defaults to px (back-compat for legacy/no-unit
    // boxes). Constructed explicitly: code defaults now carry a rem unit, so
    // the no-unit path must be exercised deliberately, not leeched off them.
    $pxState = TypographyResolver::defaults();
    $pxState['elements']['h2']['margin'] = ['top' => 0, 'right' => 0, 'bottom' => 24, 'left' => 0];
    expect(TypographyCompiler::compile($pxState))->toContain('margin-bottom: 24px');
});

it('migrates a legacy flat font.size passed straight to the compiler', function () {
    $state = TypographyResolver::defaults();
    $state['elements']['h1']['font']['size'] = ['value' => 4, 'unit' => 'rem']; // legacy flat

    $css = TypographyCompiler::compile($state);

    expect($css)->toContain('font-size: 4rem');                                              // xl byte-exact
    expect($css)->toContain('@media (max-width: 576px) { h1:not(nav h1) { font-size: 2.4rem; } }'); // 4 × 0.60
});

it('scopes the @media breakpoint blocks under the given prefix', function () {
    $css = TypographyCompiler::compileScoped(['.np-preview'], TypographyResolver::defaults());

    expect($css)->toContain('.np-preview h1:not(nav h1) { ');
    expect($css)->toContain('@media (max-width: 576px) { .np-preview h1:not(nav h1) { font-size: 1.5rem; } }');
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
