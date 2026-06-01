<?php

// Permanent @layer-discipline gate for the Widget Styling Contract arc
// (session 332 — the cascade-isolation rollout). The widget styling boundary
// is made robust by CSS cascade layers: host base/theme CSS lives in low
// layers (`reset`, `host`) and widget interiors in the highest (`widgets`), so
// a widget wins over a host base rule by LAYER ORDER, not selector specificity.
//
// `@layer` tolerates no partial rollout: ANY unlayered rule beats EVERY layered
// rule, so a single host CSS source that escapes its layer silently flips
// precedence sitewide. This guard pins the rollout's all-or-nothing invariant
// so a future edit cannot quietly reintroduce an unlayered host rule:
//
//   (1) Every public-bundle host SCSS partial wraps its body in `@layer host`.
//   (2) public.scss declares the canonical order (via _layers.scss) and routes
//       the reset/vendor CSS into `@layer reset`.
//   (3) The build-server bundle (AssetBuildService::collectSources) declares the
//       order, layers every host emit source (`host`) and every widget source
//       (`widgets`), and lets NO selector rule escape unlayered.
//   (4) The runtime inline nav-font-family <style> (public.blade) is layered.
//
// Operator `custom_scss` (public.blade, ScssPhp runtime) is deliberately left
// UNLAYERED so it beats every layer — the intended operator-wins exception, not
// a leak — and is therefore out of this guard by construction.
//
// Shape mirrors WidgetStyleBoundaryConsumptionTest / DesignGroupIntegrityTest:
// explicit reviewed lists, not heuristics. Editing the host CSS surface means
// editing the reviewed list here in the same pass — that is the whole point.

use App\Models\WidgetType;
use App\Services\AssetBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('design');

// The host SCSS partials that emit CSS into the public bundle and must each
// carry an `@layer host { }` wrapper. `_variables` (Sass var defs, no CSS) and
// `_layers` (the order statement itself) are excluded by construction.
const LAYERED_HOST_PARTIALS = [
    '_base.scss',
    '_layout.scss',
    '_grid.scss',
    '_forms.scss',
    '_controls.scss',
    '_buttons.scss',
    '_icons.scss',
    '_media.scss',
    '_custom.scss',
];

// Strip CSS/SCSS comments so a layer mention inside a comment is never counted
// as real layering, and a brace inside a comment never skews the depth walk.
function stripScssComments(string $src): string
{
    $src = preg_replace('#/\*.*?\*/#s', '', $src);          // block comments
    $src = preg_replace('#//.*$#m', '', $src);              // line comments
    return (string) $src;
}

// True when EVERY `{`-opening rule in $css sits inside an `@layer NAME { }`
// block — i.e. no selector rule escapes a layer. Sass `@layer a, b;` order
// statements and `$var:` defs carry no brace, so they are naturally ignored.
function everyRuleIsLayered(string $css): bool
{
    $css = stripScssComments($css);
    $depth = 0;
    $layerDepths = [];                 // brace-depths opened by an @layer block
    $offset = 0;
    $len = strlen($css);

    for ($i = 0; $i < $len; $i++) {
        $ch = $css[$i];
        if ($ch === '@' && preg_match('/\G@layer\b[^{};]*\{/', $css, $m, 0, $i)) {
            // an @layer NAME { block opens here
            $layerDepths[] = $depth;
            $depth++;
            $i += strlen($m[0]) - 1;
            continue;
        }
        if ($ch === '{') {
            // a non-layer block opens — it MUST already be inside a layer
            if (empty($layerDepths)) {
                return false;
            }
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if (! empty($layerDepths) && end($layerDepths) === $depth) {
                array_pop($layerDepths);
            }
        }
    }

    return true;
}

/** collectSources() is protected; the bundle's exact source set is the artifact. */
function collectBundleSources(): array
{
    $svc = new AssetBuildService();
    $m = new ReflectionMethod($svc, 'collectSources');
    $m->setAccessible(true);

    return $m->invoke($svc);
}

it('every public-bundle host SCSS partial wraps its body in @layer host', function () {
    $dir = resource_path('scss');
    $missing = [];

    foreach (LAYERED_HOST_PARTIALS as $partial) {
        $src = stripScssComments((string) file_get_contents($dir . '/' . $partial));
        if (! str_contains($src, '@layer host')) {
            $missing[] = $partial;
        }
    }

    expect($missing)->toBe(
        [],
        "Host SCSS partial(s) not wrapped in `@layer host` — an unlayered host rule "
        . "beats every layered widget rule and silently flips precedence sitewide. "
        . "Wrap the body in `@layer host { }` (or update LAYERED_HOST_PARTIALS if the "
        . "bundle surface changed): " . implode(', ', $missing),
    );

    // The reviewed list must equal the actual public-bundle partials, minus
    // `_variables` (vars only) and `_layers` (the order statement).
    $actual = collect(glob($dir . '/_*.scss'))
        ->map(fn ($p) => basename($p))
        ->reject(fn ($n) => in_array($n, ['_variables.scss', '_layers.scss'], true))
        ->sort()->values()->all();

    $expected = collect(LAYERED_HOST_PARTIALS)->sort()->values()->all();

    expect($actual)->toBe(
        $expected,
        'The set of host SCSS partials changed — add/remove it in LAYERED_HOST_PARTIALS '
        . 'in the same reviewed pass so the layer-discipline guard stays exact.',
    );
});

it('public.scss declares the cascade order and layers the reset/vendor CSS', function () {
    $publicScss = (string) file_get_contents(resource_path('scss/public.scss'));
    $layersScss = (string) file_get_contents(resource_path('scss/_layers.scss'));

    expect($publicScss)->toContain('@use "layers"');     // order declared first
    expect($publicScss)->toContain('@layer reset');      // reset/vendor layered
    expect(stripScssComments($layersScss))->toContain('@layer reset, host, widgets;');
});

it('the build-server bundle layers every host + widget source and nothing escapes', function () {
    // Seed a widget type whose SCSS asset points at a real on-disk widget file,
    // so collectSources() actually emits a widget source to layer (the test DB
    // carries no widget_types otherwise).
    $widgetScss = collect(glob(base_path('app/Widgets/*/styles.scss')))->first();
    expect($widgetScss)->not->toBeNull();
    $relScss = ltrim(str_replace(base_path(), '', $widgetScss), '/');

    WidgetType::create([
        'handle' => 'layer_guard_probe',
        'label' => 'Layer Guard Probe',
        'category' => ['content'],
        'assets' => ['scss' => [$relScss]],
    ]);

    $sources = collectBundleSources();
    $scss = collect($sources['scss'] ?? []);

    $theme = $scss->firstWhere('path', 'theme/public.scss');
    expect($theme)->not->toBeNull();

    // The combined host source declares the canonical order first…
    expect($theme['content'])->toStartWith('@layer reset, host, widgets;');
    // …and no selector rule in it escapes a layer (host partials + the runtime
    // typography/colour/button emit are all inside `@layer host`).
    expect(everyRuleIsLayered($theme['content']))->toBeTrue(
        'A rule in the combined host bundle source escapes its cascade layer — '
        . 'an unlayered host rule beats every layered widget rule. Wrap the runtime '
        . 'emit (typography / colour / button overrides) in `@layer host { }`.',
    );

    // Every widget SCSS source is in the `widgets` layer and restates the order.
    $widgetSources = $scss->filter(fn ($e) => str_starts_with($e['path'], 'widgets/'));
    expect($widgetSources)->not->toBeEmpty();

    $unlayered = $widgetSources
        ->reject(fn ($e) => str_contains($e['content'], '@layer widgets {')
            && str_contains($e['content'], '@layer reset, host, widgets;')
            && everyRuleIsLayered($e['content']))
        ->pluck('path')->all();

    expect($unlayered)->toBe(
        [],
        'Widget SCSS source(s) not wrapped in the `widgets` cascade layer: '
        . implode(', ', $unlayered),
    );
});

it('the runtime inline nav-font-family <style> is wrapped in @layer host', function () {
    $blade = (string) file_get_contents(resource_path('views/layouts/public.blade.php'));

    // The nav-family rule is emitted inside a `@layer host { }` block so no
    // host-side rule-based <style> escapes the cascade layers.
    expect($blade)->toContain('<style>@layer host { .np-site nav, .np-site nav a { font-family:');
});
