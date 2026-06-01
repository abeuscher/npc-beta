<?php

// Standing regression gate for session 334 (Mobile Nav). The mobile menu is a
// CSS-only drop-down: a visually-hidden toggle checkbox holds open/closed state,
// and the stylesheet reveals an absolutely-positioned panel off `:checked`.
// No JS, no fixed overlay, no document scroll-lock — the nav is never fixed or
// sticky, so the drop lives in the document and scrolls with the page. Source-
// level (not compiled-bundle) on purpose: the widget build pipeline does not run
// in CI (333 finding), so a bundle-read guard would be untrustworthy there.
//
//   1. Reveal. The toggle checkbox must reveal the panel
//      (`.widget-nav__toggle:checked ~ .widget-nav__mobile { display: ... }`).
//   2. Absolute, not fixed. The panel must be `position: absolute` so it drops
//      in-document below the header and scrolls with the page. A `position:
//      fixed` overlay is the pattern that forced the scroll-lock / scrollbar /
//      hop problems this session deliberately moved away from.

use Tests\TestCase;

uses(TestCase::class)->group('design');

function navWidgetScssSource(): string
{
    $abs = dirname(__DIR__, 2) . '/app/Widgets/Nav/styles.scss';
    $src = (string) file_get_contents($abs);

    // Strip comments so a commented example can never satisfy or trip a check.
    $src = preg_replace('#/\*.*?\*/#s', '', $src); // block comments
    $src = preg_replace('#//.*$#m', '', $src);      // line comments

    return $src;
}

it('reveals the mobile menu from the toggle checkbox (CSS-only, no JS)', function () {
    $src = navWidgetScssSource();

    expect($src)->toMatch(
        '/\.widget-nav__toggle:checked\s*~\s*\.widget-nav__mobile\s*\{[^}]*display\s*:\s*block/s',
        'The mobile menu must be revealed by the toggle checkbox '
        . '(.widget-nav__toggle:checked ~ .widget-nav__mobile { display: block }). '
        . 'Without it the CSS-only hamburger cannot open.',
    );
});

it('drops the mobile menu in-document via position:absolute, not a fixed overlay', function () {
    $src = navWidgetScssSource();

    preg_match('/\.widget-nav__mobile\s*\{([^}]*)\}/', $src, $m);
    $body = $m[1] ?? '';

    expect($body)->toMatch(
        '/position\s*:\s*absolute/',
        'The mobile menu must be position: absolute so it drops in-document and '
        . 'scrolls with the page — not a fixed overlay (which reintroduces the '
        . 'scroll-lock / scrollbar / hop problems).',
    );
    expect($body)->not->toMatch(
        '/position\s*:\s*fixed/',
        'The mobile menu must not be position: fixed.',
    );
});
