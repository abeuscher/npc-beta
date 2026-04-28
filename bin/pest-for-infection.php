#!/usr/bin/env php
<?php

/*
 * Pest 2 + Infection 0.32 adapter shim. Invoked by Infection through
 * phpUnit.customPath in infection.json5 because Infection's PHPUnit
 * adapter spawns the configured path as `php <path>` — so this must
 * be a PHP file, not a shell script.
 *
 * Why this exists: PCOV's coverage-xml emits Pest's mangled namespace
 * ("P\Tests\Feature\..."). PHPUnit's junit log emits the user-facing
 * namespace ("Tests\Feature\..."). Infection cross-references the two
 * by class name and the prefix mismatch makes the lookup fail. The
 * shim runs Pest, then rewrites the junit log so both sides agree on
 * the "P\" form.
 *
 * Pest needs PCOV loaded for coverage-xml emission, so the inner
 * invocation hard-codes the PCOV opt-in flags (matching the outer
 * flags the workflow doc names).
 */

$args = array_slice($argv, 1);

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
