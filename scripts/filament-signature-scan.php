<?php

/**
 * Filament signature drift scan (Code Review & Cleanup, Cycle 3 — session 344).
 *
 * Reflects every app Filament subclass (Resources, Pages, RelationManagers,
 * Widgets) against its framework ancestors and reports methods that LOOK like
 * framework overrides but override nothing — the inert-dead-code class of bug
 * (Filament 2 `getRelationManagers()` vs Filament 3 `getRelations()`, surfaced
 * at session 275). Such a method compiles cleanly, breaks no test, and is never
 * called by the framework, so grep + the horizontal sweeps miss it.
 *
 * Detection is advisory, not authoritative — it surfaces candidates for human
 * review, not auto-fix. Three signals:
 *   1. KNOWN_BAD       — exact names known to be removed/renamed by Filament v3.
 *   2. common-prefix   — declared method shares a long prefix with an ancestor
 *                        method but is not an exact match and overrides nothing.
 *   3. levenshtein<=2  — declared method is a near-typo of an ancestor method.
 *
 * Read-only. Run inside the app container:
 *   docker compose exec app php scripts/filament-signature-scan.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Filament method names removed/renamed in v3 that are dead if declared today.
 * Deliberately tight: the table-hook names (getTableQuery / getTableColumns / …)
 * are LEGITIMATE v3 overrides on custom Pages that use the InteractsWithTable
 * trait, so they are not listed here. `getRelationManagers` is the canonical
 * inert-dead-code bug (session 275); it has no valid v3 meaning on a Resource.
 */
const KNOWN_BAD = [
    'getRelationManagers' => 'getRelations',          // v2 -> v3, the 275 bug class
];

/** Method-name prefixes that signal "this is meant to be a framework hook". */
const HOOK_PREFIXES = ['get', 'can', 'is', 'should', 'mutate', 'boot', 'resolve', 'has', 'form', 'table', 'infolist'];

/** Map an app/ path to its PSR-4 FQCN. */
function fqcn(string $path): string
{
    $rel = preg_replace('#^.*/app/#', '', $path);
    $rel = preg_replace('#\.php$#', '', $rel);
    return 'App\\' . str_replace('/', '\\', $rel);
}

$globs = [
    __DIR__ . '/../app/Filament/Resources/*.php',
    __DIR__ . '/../app/Filament/Resources/*/Pages/*.php',
    __DIR__ . '/../app/Filament/Resources/*/RelationManagers/*.php',
    __DIR__ . '/../app/Filament/Pages/*.php',
    __DIR__ . '/../app/Filament/Pages/*/*.php',
    __DIR__ . '/../app/Filament/Widgets/*.php',
];

$classes = [];
foreach ($globs as $g) {
    foreach (glob($g) ?: [] as $file) {
        $fqcn = fqcn($file);
        if (class_exists($fqcn)) {
            $classes[$fqcn] = true;
        }
    }
}
ksort($classes);

$findings = [];
$scanned = 0;

foreach (array_keys($classes) as $fqcn) {
    try {
        $rc = new ReflectionClass($fqcn);
    } catch (\Throwable $e) {
        continue;
    }
    if ($rc->isAbstract() || ! $rc->getParentClass()) {
        continue;
    }
    $scanned++;

    // Collect ancestor (framework) method names.
    $ancestorMethods = [];
    for ($p = $rc->getParentClass(); $p; $p = $p->getParentClass()) {
        foreach ($p->getMethods() as $m) {
            $ancestorMethods[$m->getName()] = true;
        }
    }
    $ancestorNames = array_keys($ancestorMethods);

    foreach ($rc->getMethods() as $m) {
        // Only methods physically declared in THIS class.
        if ($m->getDeclaringClass()->getName() !== $fqcn) {
            continue;
        }
        // PHP flattens trait methods onto the using class (getDeclaringClass
        // then reports the using class), so filter to methods whose body lives
        // in the class's own file — excludes Filament's InteractsWithTable etc.
        if ($m->getFileName() !== $rc->getFileName()) {
            continue;
        }
        $name = $m->getName();
        if (str_starts_with($name, '__')) {
            continue;
        }
        // Overrides an ancestor -> wired correctly, skip.
        if (isset($ancestorMethods[$name])) {
            continue;
        }

        // Signal 1: known-bad exact name.
        if (isset(KNOWN_BAD[$name])) {
            $findings[] = sprintf('  [KNOWN-BAD]   %s::%s()  -> use %s', $rc->getShortName(), $name, KNOWN_BAD[$name]);
            continue;
        }

        // Only consider hook-shaped names for the fuzzy signals (cuts noise).
        $isHookShaped = false;
        foreach (HOOK_PREFIXES as $pre) {
            if (str_starts_with($name, $pre)) {
                $isHookShaped = true;
                break;
            }
        }
        if (! $isHookShaped) {
            continue;
        }

        // Signals 2 + 3 against ancestor methods.
        $bestPrefix = 0;
        $bestPrefixName = '';
        $bestLev = PHP_INT_MAX;
        $bestLevName = '';
        foreach ($ancestorNames as $an) {
            // common prefix
            $cp = 0;
            $max = min(strlen($name), strlen($an));
            while ($cp < $max && $name[$cp] === $an[$cp]) {
                $cp++;
            }
            if ($cp > $bestPrefix) {
                $bestPrefix = $cp;
                $bestPrefixName = $an;
            }
            // levenshtein
            $lev = levenshtein($name, $an);
            if ($lev < $bestLev) {
                $bestLev = $lev;
                $bestLevName = $an;
            }
        }

        if ($bestLev <= 2 && $bestLevName !== '') {
            $findings[] = sprintf('  [near-typo]   %s::%s()  ~= ancestor %s() (lev %d)', $rc->getShortName(), $name, $bestLevName, $bestLev);
        } elseif ($bestPrefix >= 8 && $bestPrefixName !== '') {
            $findings[] = sprintf('  [prefix-near] %s::%s()  shares %d-char prefix with ancestor %s()', $rc->getShortName(), $name, $bestPrefix, $bestPrefixName);
        }
    }
}

echo "Filament signature drift scan — {$scanned} subclasses reflected\n";
echo str_repeat('-', 70) . "\n";
if (empty($findings)) {
    echo "No suspected dead-override / signature-drift candidates found.\n";
} else {
    echo implode("\n", $findings) . "\n";
    echo "\n" . count($findings) . " candidate(s) — review each by hand (advisory, not authoritative).\n";
}
