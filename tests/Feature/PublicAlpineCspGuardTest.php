<?php

// Convention-drift guard for the session-375 CSP migration.
//
// The public/portal surface runs Alpine's CSP-safe build, because the enforced
// public Content-Security-Policy (session 370, Security S1) grants no
// 'unsafe-eval'. That build parses directive expressions itself instead of
// handing them to `new Function()`, and its grammar is a strict subset of
// JavaScript. Four shapes are not expressible in it:
//
//   1. method definitions inside an inline `x-data` object
//   2. arrow functions anywhere in a directive
//   3. `if` statements and multi-statement handlers
//   4. optional chaining, and any reference to a `window` global
//
// A template that uses one does not fail the build — it fails silently in the
// browser at runtime, which is exactly how the original defect went unnoticed
// from the S1 deploy until session 374. Widget JS therefore lives in registered
// components (`app/Widgets/{Name}/script.js` → `window.NPWidgets.*`, registered
// by resources/js/shared/widget-components.js) and templates reference them by
// name.
//
// This guard is the static half of the net; the browser half is
// tests/e2e/public-console-errors.spec.ts. It runs in every environment because
// it needs neither a browser nor the built widget bundle.
//
// The admin panel is deliberately out of scope: it keeps its own Alpine build
// and its CSP carve-out for 'unsafe-eval' (Filament requires it), so
// resources/views/filament/** and resources/views/livewire/** are not scanned.

uses(Tests\TestCase::class);

/**
 * Public-surface Blade files: every widget template plus the shared public
 * layouts and components. Admin-only Blade lives elsewhere and is skipped.
 */
function publicSurfaceBladeFiles(): array
{
    // resources/views/components holds both surfaces' shared components, so the
    // admin-only ones are named here rather than by directory. Scanning is
    // opt-out by design: a new component is covered unless it is listed, which
    // fails safe — the cost of a wrong entry is a missed check on the surface
    // where the CSP is actually enforced.
    //
    // Each of these renders only inside the admin panel, which keeps standard
    // Alpine and the 'unsafe-eval' carve-out Filament needs:
    //   permission-matrix    — components/admin, admin resource pages
    //   help-slide-over      — AdminPanelProvider render hook
    //   widget-picker-modal  — the Livewire page/dashboard builders
    //   loading-link         — the Filament import wizard pages
    $adminOnly = [
        'resources/views/components/admin/permission-matrix.blade.php',
        'resources/views/components/help-slide-over.blade.php',
        'resources/views/components/widget-picker-modal.blade.php',
        'resources/views/components/loading-link.blade.php',
        'resources/views/components/admin-fullscreen-toggle.blade.php',
    ];

    $roots = [
        base_path('app/Widgets'),
        base_path('resources/views/components'),
        base_path('resources/views/layouts'),
        base_path('resources/views/portal'),
        base_path('resources/views/pages'),
        base_path('resources/views/posts'),
        base_path('resources/views/events'),
        base_path('resources/views/widget-shared'),
    ];

    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (! in_array(relativeGuardPath($file->getPathname()), $adminOnly, true)) {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * Extract Alpine directive attributes and their expression values.
 *
 * Only unambiguously-Alpine attributes are returned: `x-*`, `@event`, and
 * `:binding`. Blade's own control directives (`@if`, `@foreach`, `@json`) never
 * match, because the pattern requires an `="…"` value immediately after the
 * attribute name.
 *
 * @return array<int, array{attr: string, value: string, line: int}>
 */
function alpineDirectives(string $contents): array
{
    $pattern = '/(?<attr>(?:x-[a-zA-Z0-9:._-]+|@[a-zA-Z][a-zA-Z0-9:._-]*|:[a-zA-Z][a-zA-Z0-9:._-]*))\s*=\s*"(?<value>[^"]*)"/s';

    preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    $found = [];

    foreach ($matches as $match) {
        $found[] = [
            'attr'  => $match['attr'][0],
            'value' => $match['value'][0],
            'line'  => substr_count(substr($contents, 0, $match['attr'][1]), "\n") + 1,
        ];
    }

    return $found;
}

/** Event handlers and reactive hooks — the attributes that carry statements. */
function isHandlerDirective(string $attr): bool
{
    return str_starts_with($attr, '@')
        || str_starts_with($attr, 'x-on:')
        || in_array($attr, ['x-effect', 'x-init'], true);
}

it('declares no inline Alpine methods in public x-data attributes', function () {
    $offenders = [];

    foreach (publicSurfaceBladeFiles() as $file) {
        foreach (alpineDirectives(file_get_contents($file)) as $directive) {
            if ($directive['attr'] !== 'x-data') {
                continue;
            }

            // `name() {` / `async name() {` — a shorthand method definition.
            if (preg_match('/(?:async\s+)?[a-zA-Z_$][\w$]*\s*\([^)]*\)\s*\{/', $directive['value'])) {
                $offenders[] = relativeGuardPath($file) . ':' . $directive['line'];
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['Inline x-data methods are not expressible in the CSP-safe Alpine build.'],
        ['Move the component into app/Widgets/{Name}/script.js and reference it by name.'],
        $offenders
    )));
});

it('uses no arrow functions, statements, or optional chaining in public Alpine handlers', function () {
    $offenders = [];

    foreach (publicSurfaceBladeFiles() as $file) {
        foreach (alpineDirectives(file_get_contents($file)) as $directive) {
            if (! isHandlerDirective($directive['attr'])) {
                continue;
            }

            $value = $directive['value'];
            $where = relativeGuardPath($file) . ':' . $directive['line'] . ' (' . $directive['attr'] . ')';

            if (str_contains($value, '=>')) {
                $offenders[] = $where . ' — arrow function';
            }
            if (preg_match('/\bif\s*\(/', $value)) {
                $offenders[] = $where . ' — if statement';
            }
            if (preg_match('/;\s*\S/', $value)) {
                $offenders[] = $where . ' — multiple statements';
            }
            if (str_contains($value, '?.')) {
                $offenders[] = $where . ' — optional chaining';
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['These shapes parse under standard Alpine but not under the CSP-safe build.'],
        ['Move the logic into a component method and reference the method here.'],
        $offenders
    )));
});

it('reaches no window globals from public Alpine expressions', function () {
    $offenders = [];

    // `@js()` compiles to `JSON.parse('…')`, which needs the `JSON` global.
    $globals = ['NPWidgets', 'JSON.', 'window.', 'navigator.', 'document.', '@js('];

    foreach (publicSurfaceBladeFiles() as $file) {
        foreach (alpineDirectives(file_get_contents($file)) as $directive) {
            foreach ($globals as $global) {
                if (str_contains($directive['value'], $global)) {
                    $offenders[] = relativeGuardPath($file) . ':' . $directive['line']
                        . ' (' . $directive['attr'] . ') — ' . $global;
                }
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['The CSP-safe Alpine build resolves expressions against the component scope only.'],
        ['Read globals inside the component instead; pass Blade values via a JSON config block.'],
        $offenders
    )));
});

it('uses no x-html on the public surface', function () {
    $offenders = [];

    foreach (publicSurfaceBladeFiles() as $file) {
        foreach (alpineDirectives(file_get_contents($file)) as $directive) {
            if ($directive['attr'] === 'x-html') {
                $offenders[] = relativeGuardPath($file) . ':' . $directive['line'];
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['x-html is refused outright by the CSP-safe Alpine build.'],
        $offenders
    )));
});

it('registers every widget Alpine component referenced by a template', function () {
    $registered = [];

    foreach (glob(base_path('app/Widgets/*/script.js')) as $script) {
        preg_match_all('/window\.NPWidgets\.([a-zA-Z0-9_$]+)\s*=/', file_get_contents($script), $matches);
        $registered = array_merge($registered, $matches[1]);
    }

    // Registered outside the widget folders — public.js and admin.js both bind it.
    $registered[] = 'customSelect';

    $missing = [];

    foreach (glob(base_path('app/Widgets/*/template.blade.php')) as $template) {
        foreach (alpineDirectives(file_get_contents($template)) as $directive) {
            if ($directive['attr'] !== 'x-data') {
                continue;
            }

            $value = trim($directive['value']);

            // Inline data-only objects (`{ open: false }`) name no component.
            if ($value === '' || str_starts_with($value, '{')) {
                continue;
            }

            $name = preg_replace('/\(.*$/s', '', $value);

            if (! in_array($name, $registered, true)) {
                $missing[] = relativeGuardPath($template) . ':' . $directive['line'] . ' — ' . $name;
            }
        }
    }

    expect($missing)->toBe([], implode("\n", array_merge(
        ['A template references an Alpine component that no widget script registers.'],
        ['Under the CSP-safe build this throws "Undefined variable" and the widget is dead.'],
        $missing
    )));
});

function relativeGuardPath(string $path): string
{
    return str_replace(base_path() . '/', '', $path);
}
