#!/usr/bin/env php
<?php

/*
 * Pest 2 + Infection 0.32 adapter shim. Invoked by Infection through
 * phpUnit.customPath in infection.json5 because Infection's PHPUnit
 * adapter spawns the configured path as `php <path>` — so this must
 * be a PHP file, not a shell script.
 *
 * Two compatibility fixes are applied:
 *
 * 1. Per-mutant config <file> path rewrite. Infection generates a
 *    PHPUnit config under /tmp/infection/ for each mutant with
 *    relative test paths ("tests/Feature/X.php"). PHPUnit/Pest
 *    resolve those relative to the config file's location, so
 *    "/tmp/infection/tests/Feature/X.php" — which does not exist —
 *    causes a "Test file not found" exit and a false-positive
 *    "killed" status. The shim rewrites every <file> in any
 *    /tmp/infection/ config to an absolute project path before Pest
 *    sees it.
 *
 * 2. Junit log namespace rewrite. PCOV's coverage-xml emits Pest's
 *    mangled namespace ("P\Tests\Feature\..."). PHPUnit's junit log
 *    emits the user-facing namespace ("Tests\Feature\..."). Infection
 *    cross-references the two by class name and the prefix mismatch
 *    makes the lookup fail. The shim rewrites the junit log so both
 *    sides agree on the "P\" form.
 *
 * Pest needs PCOV loaded for coverage-xml emission, so the inner
 * invocation hard-codes the PCOV opt-in flags (matching the outer
 * flags the workflow doc names).
 */

$args = array_slice($argv, 1);

$projectRoot = getcwd();
$configPath = null;
foreach ($args as $i => $arg) {
    if ($arg === '--configuration' && isset($args[$i + 1])) {
        $configPath = $args[$i + 1];
        break;
    }
    if (str_starts_with($arg, '--configuration=')) {
        $configPath = substr($arg, strlen('--configuration='));
        break;
    }
}

if ($configPath !== null && is_file($configPath) && str_contains($configPath, '/infection/')) {
    $cfg = file_get_contents($configPath);
    $rewritten = preg_replace_callback(
        '#<file>([^<]+)</file>#',
        function ($m) use ($projectRoot) {
            $path = $m[1];
            if ($path === '' || $path[0] === '/') {
                return $m[0];
            }
            return '<file>' . $projectRoot . '/' . $path . '</file>';
        },
        $cfg
    );
    if (getenv('PEST_FOR_INFECTION_NO_STOP_ON_FAILURE') === '1') {
        $rewritten = preg_replace('/stopOnFailure="true"/', 'stopOnFailure="false"', $rewritten);
    }
    if ($rewritten !== $cfg) {
        file_put_contents($configPath, $rewritten);
    }
}

$junitPath = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--log-junit=')) {
        $junitPath = substr($arg, strlen('--log-junit='));
        break;
    }
}

$cmd = 'php -d memory_limit=4G -d extension=pcov -d pcov.enabled=1 vendor/bin/pest '
    . implode(' ', array_map('escapeshellarg', $args));

passthru($cmd, $exit);

if ($junitPath !== null && is_file($junitPath)) {
    $contents = file_get_contents($junitPath);
    $contents = str_replace(
        ['name="Tests\\', 'class="Tests\\', 'classname="Tests.'],
        ['name="P\\Tests\\', 'class="P\\Tests\\', 'classname="P.Tests.'],
        $contents
    );
    file_put_contents($junitPath, $contents);
}

exit($exit);
