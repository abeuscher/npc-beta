<?php

// Objective oracle + permanent regression gate for the Theme/Template
// Re-Taxonomy arc's risk session (300): widget SCSS must consume the
// canonical `--np-color-*` contract (docs/theme-color-tokens.md), never a
// raw hex literal or a bare `$color-*` SCSS variable. A widget that hardcodes
// a hex receives neither the Theme default nor any override — the exact
// silent-failure class that consumed session 295.
//
// Shape mirrors the convention-drift gate (DesignGroupIntegrityTest): an
// explicit, reviewed baseline list — NOT a heuristic. It fails both ways:
// a NEW hardcoded colour anywhere in the widget SCSS corpus (regression),
// and a baseline entry that no longer exists (migration progress that was
// not recorded in the same reviewed pass). Editing the corpus means editing
// this baseline in the same pass — that is the whole point.
//
// The corpus is every `app/Widgets/*/*.scss` file — a superset-safe mirror
// of what AssetBuildService::collectSources() folds into the public bundle
// (it pulls the WidgetType `assets.scss`-declared files; globbing the tree
// also catches a new widget SCSS file before it is registered).
//
// SESSION-300 OUTCOME: the Phase-1 survey found 44 hit lines (15 already
// token-backed [class a], 27 raw-hex silent-failure [class b], 2
// scope-fenced gradient stops [class c]). Phase 2 migrated all 39 class-a
// + class-b lines to `var(--np-color-*)`. This baseline is now the final,
// permanent allow-list: the ONLY hardcoded colours the corpus may carry,
// each a reviewed, deliberate scope-fenced exception. Zero class-a/class-b
// remains — the silent-failure class is closed. Any NEW hardcoded colour
// fails this gate; migrating one of the five exceptions means deleting it
// from this list in the same reviewed pass.

use Tests\TestCase;

uses(TestCase::class)->group('design');

// ── Scanner ──────────────────────────────────────────────────────────────
// Single source of truth for "what counts as a hardcoded colour in widget
// SCSS". Strips block + line comments first so a commented-out hex or a
// doc-comment colour name is never a false positive. Returns an ordered
// list of "relative/path.scss:LINE" keys for every offending line.
function widgetScssColorHits(): array
{
    $root  = dirname(__DIR__, 2);
    $files = glob($root . '/app/Widgets/*/*.scss');
    sort($files);

    $hits = [];
    foreach ($files as $abs) {
        $rel = ltrim(str_replace($root, '', $abs), '/');
        $src = (string) file_get_contents($abs);
        $src = preg_replace('#/\*.*?\*/#s', '', $src); // block comments

        foreach (explode("\n", $src) as $i => $line) {
            $code = preg_replace('#//.*$#', '', $line); // line comment
            $hasHex  = preg_match('/#[0-9a-fA-F]{3,8}\b/', (string) $code);
            $hasScss = preg_match('/\$color-[a-z0-9-]+/', (string) $code);
            if ($hasHex || $hasScss) {
                $hits[] = $rel . ':' . ($i + 1);
            }
        }
    }

    return $hits;
}

// ── Reviewed allow-list ──────────────────────────────────────────────────
// The five — and ONLY five — hardcoded colours the widget SCSS corpus may
// carry after the session-300 migration. Each is a deliberate, reviewed
// scope-fenced exception, NOT a missed migration:
//
//   ProductCarousel :25 :30 — `#000000` is a stop inside a `linear-gradient`
//     edge-fade. Gradients are explicitly out of the colour-token contract
//     (scope fence); logged to the housekeeping inbox, not migrated.
//
//   EventsListing :96 — `color: #fff` on the badge's `--np-color-success`
//     fill. There is no `success-contrast` token; white is the deliberate
//     legible-on-dark-success accent (confirmed at survey, judgment #3).
//
//   MapEmbed :39 / SocialSharing :62 — `color: #fff` on a black `rgba(...)`
//     scrim overlay. Scrim-contrast white is theme-independent by design;
//     the scrim itself is a non-colour rgba (out of contract). Deliberate
//     accent (judgment #4).
//
// Everything else migrated to `var(--np-color-*)`. Migrating one of these
// five means deleting it here in the same reviewed pass.
const WIDGET_SCSS_COLOR_BASELINE = [
    'app/Widgets/EventsListing/styles.scss:96',   // #fff on --np-color-success fill (judgment #3)
    'app/Widgets/MapEmbed/styles.scss:39',        // #fff on rgba scrim (judgment #4)
    'app/Widgets/ProductCarousel/styles.scss:25', // #000000 gradient stop (scope-fenced)
    'app/Widgets/ProductCarousel/styles.scss:30', // #000000 gradient stop (scope-fenced)
    'app/Widgets/SocialSharing/styles.scss:62',   // #fff on rgba scrim (judgment #4)
];

it('widget SCSS colour hits match the reviewed session-300 baseline', function () {
    $current  = widgetScssColorHits();
    $baseline = WIDGET_SCSS_COLOR_BASELINE;
    sort($baseline);
    sort($current);

    $added   = array_values(array_diff($current, $baseline));
    $removed = array_values(array_diff($baseline, $current));

    expect($added)->toBe([], "NEW hardcoded colour(s) in widget SCSS — every widget colour must read var(--np-color-*) (docs/theme-color-tokens.md). Offending line(s):\n  " . implode("\n  ", $added));

    expect($removed)->toBe([], "Baseline entries no longer present — migration progress must be recorded by deleting these from WIDGET_SCSS_COLOR_BASELINE in the same reviewed pass:\n  " . implode("\n  ", $removed));
});
