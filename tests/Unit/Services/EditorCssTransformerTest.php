<?php

use App\Services\EditorCssTransformer;

uses()->group('design');

it('rewrites a max-width media rule to a named container rule', function () {
    $css = '@media (max-width: 768px) { .widget { padding-top: 10px; } }';

    expect(EditorCssTransformer::transform($css))
        ->toBe('@container np-viewport (max-width: 768px) { .widget { padding-top: 10px; } }');
});

it('rewrites min-width and combined width-only conditions', function () {
    $css = '@media (min-width: 576px) and (max-width: 991px) { .a { color: red; } }';

    expect(EditorCssTransformer::transform($css))
        ->toBe('@container np-viewport (min-width: 576px) and (max-width: 991px) { .a { color: red; } }');
});

it('strips screen media-type prefixes from width-only rules', function () {
    expect(EditorCssTransformer::transform('@media screen and (max-width: 768px) { .a { color: red; } }'))
        ->toBe('@container np-viewport (max-width: 768px) { .a { color: red; } }');

    expect(EditorCssTransformer::transform('@media only screen and (min-width: 992px) { .a { color: red; } }'))
        ->toBe('@container np-viewport (min-width: 992px) { .a { color: red; } }');
});

it('handles minified preludes without spaces', function () {
    $css = '@media(max-width:768px){.widget{margin:0}}';

    expect(EditorCssTransformer::transform($css))
        ->toBe('@container np-viewport (max-width:768px) {.widget{margin:0}}');
});

it('leaves non-width media rules untouched', function () {
    $print = '@media print { .a { display: none; } }';
    $motion = '@media (prefers-reduced-motion: reduce) { .a { animation: none; } }';

    expect(EditorCssTransformer::transform($print))->toBe($print);
    expect(EditorCssTransformer::transform($motion))->toBe($motion);
});

it('leaves width conditions mixed with non-width features untouched', function () {
    $css = '@media (max-width: 768px) and (orientation: landscape) { .a { color: red; } }';

    expect(EditorCssTransformer::transform($css))->toBe($css);
});

it('transforms every eligible block in a multi-rule sheet and skips the rest', function () {
    $css = implode("\n", [
        '@layer host {',
        '@media (max-width: 768px) { .widget { padding-top: 8px; } }',
        '@media (max-width: 576px) { .widget { padding-top: 6.4px; } }',
        '}',
        '@media print { .chrome { display: none; } }',
        '.base { color: blue; }',
    ]);

    $out = EditorCssTransformer::transform($css);

    expect($out)->toContain('@container np-viewport (max-width: 768px) { .widget { padding-top: 8px; } }');
    expect($out)->toContain('@container np-viewport (max-width: 576px) { .widget { padding-top: 6.4px; } }');
    expect($out)->toContain('@media print { .chrome { display: none; } }');
    expect($out)->toContain('.base { color: blue; }');
    expect(substr_count($out, '@container np-viewport'))->toBe(2);
});
