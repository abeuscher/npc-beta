<?php

// Standing guard for the widget template boundary (session 378, Plugin
// Architecture arc P2 — docs/plugin-contract.md surface 2). Widget templates
// are pure renderers: they read $config / $configMedia / $widgetData /
// $pageContext and render-layer helpers only. Reaching models, the container,
// auth state, or services from a template bypasses the declared data-contract
// path — the fail-closed field projection, the permission/auth gates in the
// resolver arms, and the plugin boundary all depend on templates not doing
// this. There is NO allowlist by design: a widget that needs data declares a
// contract (dataContract()/dataContracts() on its definition) and a resolver
// arm provides it. See the twelve-template retrofit in the session-378 log
// for worked examples of every category (model query, brand read, portal
// auth state, service-backed tool).

use Tests\TestCase;

uses(TestCase::class);

// Each banned reach as a PCRE. Word-ish boundaries prevent false positives on
// identifiers that merely end in the same letters (array_map( vs app().
const WIDGET_TEMPLATE_BANNED_PATTERNS = [
    '\App\Models'  => '/\\\\App\\\\Models/',
    'DB::'         => '/(?<![A-Za-z0-9_\\\\])DB::/',
    'Auth::'       => '/(?<![A-Za-z0-9_\\\\])Auth::/',
    'auth('        => '/(?<![A-Za-z0-9_$>])auth\s*\(/',
    'app('         => '/(?<![A-Za-z0-9_$>])app\s*\(/',
    '@inject'      => '/@inject/',
    '::where('     => '/::where\s*\(/',
    '::find('      => '/::find\s*\(/',
    'resolve('     => '/(?<![A-Za-z0-9_$>])resolve\s*\(/',
];

/**
 * @return array<int, string> repo-relative template paths in scan scope
 */
function widgetBoundaryScanTargets(): array
{
    $targets = array_merge(
        glob(base_path('app/Widgets/*/template.blade.php')) ?: [],
        glob(base_path('plugins/*/template.blade.php')) ?: [],
        // Vertical plugins nest their widget folders (the Events plugin nests Widgets/*
        // since session 381); single-widget plugins keep templates at the root.
        glob(base_path('plugins/*/Widgets/*/template.blade.php')) ?: [],
    );

    // Extracted plugin packages (session 385, arc P9) — the boundary follows
    // the code out of the repo into vendor/nonprofitcrm/*.
    foreach (extractedPluginPackageDirs() as $pkg) {
        $targets = array_merge(
            $targets,
            glob($pkg . '/template.blade.php') ?: [],
            glob($pkg . '/Widgets/*/template.blade.php') ?: [],
        );
    }

    $sharedDir = base_path('resources/views/widget-shared');
    if (is_dir($sharedDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sharedDir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $targets[] = $file->getPathname();
            }
        }
    }

    sort($targets);

    return $targets;
}

it('scans a non-empty template set covering core widgets, plugins, and shared partials', function () {
    $targets = widgetBoundaryScanTargets();

    // The in-repo set may be empty (session 388: the Events extraction emptied
    // plugins/ until the next vertical carves into it) — plugin templates are
    // in scope so long as they exist in-repo or as extracted packages.
    expect(count($targets))->toBeGreaterThanOrEqual(41)
        ->and(array_filter($targets, fn ($p) => str_contains($p, 'plugins/') || str_contains($p, 'vendor/nonprofitcrm/')))->not->toBeEmpty()
        ->and(array_filter($targets, fn ($p) => str_contains($p, 'widget-shared')))->not->toBeEmpty();
});

it('bans model, container, auth, and service reaches from every widget template', function () {
    $root = base_path() . '/';
    $violations = [];

    foreach (widgetBoundaryScanTargets() as $path) {
        $lines = file($path);
        foreach ($lines as $i => $line) {
            foreach (WIDGET_TEMPLATE_BANNED_PATTERNS as $token => $pattern) {
                if (preg_match($pattern, $line)) {
                    $violations[] = str_replace($root, '', $path) . ':' . ($i + 1) . ' — ' . $token;
                }
            }
        }
    }

    expect($violations)->toBe(
        [],
        "Widget templates must not reach models, auth state, the container, or services — "
        . "declare a data contract on the widget definition and read \$widgetData instead "
        . "(docs/plugin-contract.md surface 2; session-378 retrofit has worked examples):\n"
        . implode("\n", $violations),
    );
});
