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

it('emits heading bottom-margin as an em multiple by default and lets an explicit px value win', function () {
    $css = TypographyCompiler::compile(TypographyResolver::defaults());
    expect($css)->toContain('h1:not(nav h1) { ');
    expect($css)->toContain('margin-bottom: 0.4em');
    expect($css)->toContain('margin-bottom: 0.5em'); // h2..h6

    // Body elements get no em heading-margin — the px box still governs them.
    $pBlock = collect(explode("\n", $css))->first(fn ($l) => str_starts_with($l, 'p:not(nav p) { '));
    expect($pBlock)->toContain('margin-bottom: 0px');

    // An explicitly-tuned px bottom margin on a heading overrides the em default.
    $state = TypographyResolver::defaults();
    $state['elements']['h1']['margin']['bottom'] = 16;
    $css2  = TypographyCompiler::compile($state);
    $h1    = collect(explode("\n", $css2))->first(fn ($l) => str_starts_with($l, 'h1:not(nav h1) { '));
    expect($h1)->toContain('margin-bottom: 16px');
    expect($h1)->not->toContain('margin-bottom: 0.4em');
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
