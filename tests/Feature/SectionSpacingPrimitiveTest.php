<?php

// Standing regression gate for session 335 (Mobile Vertical Spacing). The
// section-spacing primitive emits the operator's VERTICAL section padding/margin
// as --np-* custom properties (never literal padding/margin declarations), so a
// single host-layer @media rule can scale them down at tablet/mobile widths —
// inline literals always beat a stylesheet, which is exactly why nothing could
// compress them before. Source-level (not compiled-bundle) on purpose: the
// widget build pipeline does not run in CI (333 finding), so a bundle-read guard
// would be untrustworthy there. The two halves locked here:
//
//   1. Emission. AppearanceStyleComposer::composeVerticalSpacingVars — the one
//      helper every render surface (widget / layout / chrome) calls — emits the
//      vertical axis as --np-* custom properties, omits a concrete-0 side
//      (preserving "0 = no override"), and never touches horizontal.
//   2. Consumption + scaling. resources/scss/_layout.scss consumes those
//      properties on .widget / .page-layout / .np-chrome-section with a 0px
//      no-override fallback, and scales them via two cascading viewport @media
//      steps keyed off a single tunable ratio.

use App\Services\AppearanceStyleComposer;
use Tests\TestCase;

uses(TestCase::class)->group('design');

function sectionSpacingScssSource(): string
{
    $abs = dirname(__DIR__, 2) . '/resources/scss/_layout.scss';
    $src = (string) file_get_contents($abs);

    // Strip comments so a commented example can never satisfy or trip a check.
    $src = preg_replace('#/\*.*?\*/#s', '', $src); // block comments
    $src = preg_replace('#//.*$#m', '', $src);      // line comments

    return $src;
}

it('emits vertical spacing as --np-* custom properties, not literal declarations', function () {
    $css = implode(';', AppearanceStyleComposer::composeVerticalSpacingVars(
        ['top' => 80, 'bottom' => 60, 'left' => 24, 'right' => 24],
        ['top' => 10, 'bottom' => 20],
    ));

    expect($css)
        ->toContain('--np-pad-top:80px')
        ->toContain('--np-pad-bottom:60px')
        ->toContain('--np-mar-top:10px')
        ->toContain('--np-mar-bottom:20px');

    // Vertical must NOT be emitted as a literal padding-top/margin-top — that is
    // the inline declaration the host rule could never override.
    expect($css)
        ->not->toContain('padding-top:')
        ->not->toContain('padding-bottom:')
        ->not->toContain('margin-top:')
        ->not->toContain('margin-bottom:');

    // Horizontal is never the primitive's concern — the helper emits no left/right.
    expect($css)
        ->not->toContain('left')
        ->not->toContain('right');
});

it('omits a concrete-0 side, preserving the "0 = no override" semantic', function () {
    $css = implode(';', AppearanceStyleComposer::composeVerticalSpacingVars(
        ['top' => 0, 'bottom' => 40],
        [],
    ));

    expect($css)
        ->toContain('--np-pad-bottom:40px')
        ->not->toContain('--np-pad-top') // a 0 side emits no property at all
        ->not->toContain('--np-mar');
});

it('consumes the --np-* properties in the host layer with a 0px no-override fallback', function () {
    $src = sectionSpacingScssSource();

    expect($src)
        ->toContain('padding-top:    var(--np-pad-top, 0px)')
        ->toContain('padding-bottom: var(--np-pad-bottom, 0px)')
        ->toContain('margin-top:     var(--np-mar-top, 0px)')
        ->toContain('margin-bottom:  var(--np-mar-bottom, 0px)');

    // The chrome-root hook must be one of the consuming selectors (chrome root
    // widgets render as a bare wrapper with no .widget class).
    expect($src)->toContain('.np-chrome-section');
});

it('resets the --np-* spacing properties to 0px on each wrapper so they cannot inherit (session 340)', function () {
    // The --np-* custom properties INHERIT. A column layout's own vertical
    // padding (e.g. --np-pad-top:50px on .page-layout) therefore bled onto every
    // child .widget whose own side was 0 — because a 0 side emits no property and
    // var(--np-pad-top, 0px) only falls back to 0px when the property is unset on
    // the element AND every ancestor. Each wrapper must reset the four properties
    // to a concrete 0px so an unset/0 child resolves to its own 0, not the
    // parent's value; a non-zero inline --np-* (an operator override) still wins,
    // because inline custom properties beat this layer'd declaration.
    $src = sectionSpacingScssSource();

    expect($src)
        ->toContain('--np-pad-top:    0px')
        ->toContain('--np-pad-bottom: 0px')
        ->toContain('--np-mar-top:    0px')
        ->toContain('--np-mar-bottom: 0px');
});

it('scales vertical spacing down at the tablet and mobile breakpoints from one tunable ratio', function () {
    $src = sectionSpacingScssSource();

    // Two cascading viewport steps (tablet $bp-md, mobile $bp-sm), each scaling
    // via calc(var * ratio) off a single source-of-truth ratio.
    expect($src)
        ->toContain('(max-width: $bp-md)')
        ->toContain('(max-width: $bp-sm)')
        ->toContain('$section-spacing-ratio: 0.8')
        ->toContain('calc(var(--np-pad-bottom, 0px) * #{$section-spacing-ratio})')
        ->toContain('calc(var(--np-pad-bottom, 0px) * #{$section-spacing-ratio * $section-spacing-ratio})');
});
