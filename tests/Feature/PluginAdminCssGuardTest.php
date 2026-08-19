<?php

// Standing guard for the core-owned admin theme (session 379, Plugin
// Architecture arc P3 — docs/plugin-contract.md surface 3, convention 3):
// plugins ship no admin CSS. Widget-scoped SCSS/JS under plugins/* (the
// LogoGarden precedent, declared via assets() and built by build:public into
// the PUBLIC bundle) is legitimate and must not trip this guard — the ban is
// on styling the admin surface: compiled .css files, Filament theme
// registration, and stylesheet injection through panel render hooks. There
// is NO allowlist by design: a plugin's admin pages inherit core's theme.

use Tests\TestCase;

uses(TestCase::class);

// Banned reaches in plugin PHP source. Word-ish boundaries mirror
// WidgetTemplateBoundaryTest's pattern style.
const PLUGIN_ADMIN_CSS_BANNED_PATTERNS = [
    'viteTheme('           => '/viteTheme\s*\(/',
    'renderHook('          => '/renderHook\s*\(/',
    '<link rel=stylesheet' => '/<link[^>]*stylesheet/i',
    'admin.css'            => '/admin[^\s\'"]*\.css/i',
];

/**
 * @return array<int, string> absolute paths of all files under plugins/ and
 *                            under extracted plugin packages in vendor/
 */
function pluginAdminCssScanTargets(): array
{
    $targets = [];

    // Extracted plugin packages (session 385, arc P9) resolve into
    // vendor/nonprofitcrm/* — the guard follows the code out of the repo.
    $roots = array_merge([base_path('plugins')], extractedPluginPackageDirs());

    foreach ($roots as $root) {
        // plugins/ may be absent entirely (session 388: the Events extraction
        // emptied it, and git tracks no empty dirs) — the extracted-package
        // roots still cover every plugin.
        if (! is_dir($root)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
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

it('scans a non-empty plugin file set that includes widget SCSS', function () {
    $targets = pluginAdminCssScanTargets();

    expect($targets)->not->toBeEmpty()
        ->and(array_filter($targets, fn ($p) => str_ends_with($p, '.scss')))->not->toBeEmpty()
        ->and(array_filter($targets, fn ($p) => str_contains($p, 'vendor/nonprofitcrm/')))->not->toBeEmpty();
});

it('bans compiled CSS files under plugins/', function () {
    $cssFiles = array_filter(pluginAdminCssScanTargets(), fn ($p) => str_ends_with($p, '.css'));

    expect(array_values($cssFiles))->toBe(
        [],
        'Plugins ship no .css files — widget styles are SCSS built into the public '
        . 'bundle by build:public, and the admin theme is core-owned '
        . '(docs/plugin-contract.md surface 3).',
    );
});

it('bans admin theme registration and stylesheet injection from plugin PHP', function () {
    $root = base_path() . '/';
    $violations = [];

    foreach (pluginAdminCssScanTargets() as $path) {
        if (! str_ends_with($path, '.php')) {
            continue;
        }

        $lines = file($path);
        foreach ($lines as $i => $line) {
            foreach (PLUGIN_ADMIN_CSS_BANNED_PATTERNS as $token => $pattern) {
                if (preg_match($pattern, $line)) {
                    $violations[] = str_replace($root, '', $path) . ':' . ($i + 1) . ' — ' . $token;
                }
            }
        }
    }

    expect($violations)->toBe(
        [],
        "Plugin admin pages inherit core's admin theme — no viteTheme, render-hook "
        . "stylesheet injection, or admin CSS references in plugin code "
        . "(docs/plugin-contract.md surface 3):\n"
        . implode("\n", $violations),
    );
});
