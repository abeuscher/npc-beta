<?php

// Permanent regression gate for the Widget Styling Contract arc (session 330).
// The widget styling boundary is "widget interior reads the published vocabulary
// and responds to ITS OWN width; it does not reach host Sass internals and does
// not key off the viewport." This pins two crossings the 330 audit surveyed:
//
//   (1) SPACING LEAK — bare `$gutter` (the one host spacing Sass var) in widget
//       SCSS. A widget that reads `$gutter` couples to a host-internal value
//       instead of owning its own. Session 330 deleted the only offender
//       (ThreeBuckets), so the baseline is EMPTY — any reintroduction fails.
//
//   (2) VIEWPORT @media — a widget interior responding to the browser viewport
//       instead of its own container width (`@container`). A viewport @media
//       never fires when the widget lands in a narrow column on a wide screen
//       (the .layout-column { min-width: 0 } blowout class). Session 330
//       migrated the always-full-width widgets (ProductCarousel, PricingChart)
//       to `@container np-widget`; the remaining offenders are an explicit,
//       reviewed baseline — the column-capable widgets whose conversion changes
//       responsive behaviour and is deferred to the mobile-appearance design
//       pass, plus EventMiniCalendar (a deliberate viewport rail-collapse).
//
// Shape mirrors WidgetColorTokenConsumptionTest / DesignGroupIntegrityTest: an
// explicit reviewed baseline, NOT a heuristic. It fails BOTH ways — a NEW
// crossing anywhere in the widget SCSS corpus (regression), and a baseline
// entry that no longer exists (migration progress not recorded in the same
// reviewed pass). Editing the corpus means editing the baseline in the same
// pass — that is the whole point.
//
// The corpus is every `app/Widgets/*/*.scss` file — the same superset-safe
// mirror of AssetBuildService::collectSources() the colour gate uses. Nav's
// breakpoint @media lives in template.blade.php (a runtime operator value
// interpolated into an inline <style>, which a @container condition cannot
// read); it is outside this scss-only corpus by construction and is the
// documented runtime-breakpoint exception.

use Tests\TestCase;

uses(TestCase::class)->group('design');

// ── Scanners ───────────────────────────────────────────────────────────────
// Strip block + line comments first so a commented-out rule or a doc-comment
// mention is never a false positive. Return ordered "relative/path.scss:LINE"
// keys for every offending line.

function widgetScssStripped(string $abs): array
{
    $src = (string) file_get_contents($abs);
    $src = preg_replace('#/\*.*?\*/#s', '', $src); // block comments

    $lines = [];
    foreach (explode("\n", $src) as $i => $line) {
        $lines[$i + 1] = (string) preg_replace('#//.*$#', '', $line); // line comment
    }

    return $lines;
}

function widgetScssGutterHits(): array
{
    $root  = dirname(__DIR__, 2);
    $files = glob($root . '/app/Widgets/*/*.scss');
    sort($files);

    $hits = [];
    foreach ($files as $abs) {
        $rel = ltrim(str_replace($root, '', $abs), '/');
        foreach (widgetScssStripped($abs) as $n => $code) {
            if (preg_match('/\$gutter\b/', $code)) {
                $hits[] = $rel . ':' . $n;
            }
        }
    }

    return $hits;
}

function widgetScssViewportMediaHits(): array
{
    $root  = dirname(__DIR__, 2);
    $files = glob($root . '/app/Widgets/*/*.scss');
    sort($files);

    $hits = [];
    foreach ($files as $abs) {
        $rel = ltrim(str_replace($root, '', $abs), '/');
        foreach (widgetScssStripped($abs) as $n => $code) {
            if (preg_match('/@media\b/', $code)) {
                $hits[] = $rel . ':' . $n;
            }
        }
    }

    return $hits;
}

// ── Reviewed baselines ───────────────────────────────────────────────────────

// Spacing: zero. ThreeBuckets (the only `$gutter` reader) was deleted at
// session 330; no widget may reintroduce a host spacing Sass var.
const WIDGET_SCSS_GUTTER_BASELINE = [];

// Viewport @media: ZERO. Session 331 migrated the last of the column-capable
// widgets (MapEmbed — heading field removed entirely; BoardMembers, LogoGarden,
// EventsListing — @media → @container np-widget) to respond to their own width.
// No widget interior may reintroduce a viewport @media; any new one fails.
// (EventMiniCalendar's `display:none` viewport rule was removed at session 330 —
// owner call, it now stacks on collapse instead of hiding. Nav's runtime-
// breakpoint @media lives in template.blade.php, outside this scss-only corpus.)
const WIDGET_SCSS_VIEWPORT_MEDIA_BASELINE = [];

it('widget SCSS carries no host spacing Sass var ($gutter)', function () {
    $current  = widgetScssGutterHits();
    $baseline = WIDGET_SCSS_GUTTER_BASELINE;
    sort($baseline);
    sort($current);

    $added   = array_values(array_diff($current, $baseline));
    $removed = array_values(array_diff($baseline, $current));

    expect($added)->toBe([], "NEW host spacing Sass var (\$gutter) in widget SCSS — a widget must own its spacing value, not read a host internal. Offending line(s):\n  " . implode("\n  ", $added));
    expect($removed)->toBe([], "Baseline entries no longer present — record migration by deleting them from WIDGET_SCSS_GUTTER_BASELINE in the same reviewed pass:\n  " . implode("\n  ", $removed));
});

it('widget SCSS viewport @media matches the reviewed deferred baseline', function () {
    $current  = widgetScssViewportMediaHits();
    $baseline = WIDGET_SCSS_VIEWPORT_MEDIA_BASELINE;
    sort($baseline);
    sort($current);

    $added   = array_values(array_diff($current, $baseline));
    $removed = array_values(array_diff($baseline, $current));

    expect($added)->toBe([], "NEW viewport @media in widget SCSS — a widget interior must respond to its own width via @container (see resources/scss/_layout.scss / resources/docs/widget-development.md), not the viewport. Offending line(s):\n  " . implode("\n  ", $added));
    expect($removed)->toBe([], "Baseline entries no longer present — record the @media → @container migration by deleting them from WIDGET_SCSS_VIEWPORT_MEDIA_BASELINE in the same reviewed pass:\n  " . implode("\n  ", $removed));
});
