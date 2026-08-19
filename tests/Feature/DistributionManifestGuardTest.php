<?php

use App\Plugins\DistributionManifest;
use App\Providers\PluginServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Distribution-manifest alignment guard (session 386, Plugin Architecture
 * arc P10 — contract surface 1). distribution.json is the authority for
 * which plugins this build composes and in what order; config/plugins.php
 * is generated from it, while composer.json and the Dockerfile stay
 * hand-maintained. This guard makes every drift loud:
 *
 *   1. Regeneration idempotence — the committed config/plugins.php is
 *      byte-identical to what the manifest generates (stale or hand-edited
 *      fails).
 *   2. Every manifest entry is real — the provider class autoloads, and
 *      in-repo source paths exist with the composer.json presence their
 *      source kind claims.
 *   3. Lock-pinning per declared source kind — path packages resolve as
 *      path dists, vcs packages as git sources with zip dists; composer.json
 *      carries the matching repository entry and the manifest's constraint.
 *   4. No unmanifested plugins — every nonprofitcrm/* package in the lock,
 *      every directory under plugins/, every plugin-manifest COPY line in
 *      the Dockerfile, and every plugins/ phpunit testsuite traces back to
 *      a manifest entry.
 */
it('generates the committed config/plugins.php byte-identically from distribution.json', function () {
    $manifest = DistributionManifest::load();

    expect(file_get_contents(base_path('config/plugins.php')))
        ->toBe($manifest->generatedConfig(), 'config/plugins.php is stale or hand-edited — edit distribution.json and run `php artisan plugins:manifest-sync`.');

    // Belt and braces: the runtime-loaded list is the manifest's list. This
    // can only diverge from the byte check via a stale config cache, which
    // never exists under phpunit — but the runtime list is what activation
    // filters, so pin it directly too.
    expect(config('plugins'))->toBe($manifest->providers());
})->group('widget-lint');

it('declares only real plugins — providers autoload, source paths exist, handles derive', function () {
    $manifest = DistributionManifest::load();

    foreach ($manifest->entries() as $entry) {
        expect(class_exists($entry['provider']))
            ->toBeTrue("{$entry['provider']} (distribution.json) does not autoload.");

        expect(PluginServiceProvider::handleFor($entry['provider']))->not->toBeEmpty();

        $type = $entry['source']['type'];

        if (in_array($type, ['folder', 'path'], true)) {
            $dir = base_path($entry['source']['path']);
            $manifestFile = $dir.'/composer.json';

            expect(is_dir($dir))->toBeTrue("{$entry['source']['path']} (distribution.json) is not a directory.");

            // A folder entry is honestly not a package; a path entry must be
            // one. Packaging a folder plugin without re-declaring its source
            // kind is drift.
            expect(file_exists($manifestFile))->toBe(
                $type === 'path',
                $type === 'path'
                    ? "{$entry['provider']} declares source.type path but {$entry['source']['path']} has no composer.json."
                    : "{$entry['provider']} declares source.type folder but {$entry['source']['path']} carries a composer.json — declare it as a path package in distribution.json."
            );

            if ($type === 'path') {
                expect(json_decode((string) file_get_contents($manifestFile), true)['name'] ?? null)
                    ->toBe($entry['package'], "{$entry['source']['path']}/composer.json package name must match the manifest entry.");
            }
        }
    }
})->group('widget-lint');

it('lock-pins every packaged manifest entry with its declared source kind', function () {
    $manifest = DistributionManifest::load();
    $root = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
    $locked = collect($lock['packages'])->keyBy('name');

    $repositories = collect($root['repositories'] ?? []);

    foreach ($manifest->entriesOfKind('path') as $entry) {
        expect($repositories->contains(fn ($r) => $r['type'] === 'path' && $r['url'] === $entry['source']['path']))
            ->toBeTrue("composer.json needs a path repository at {$entry['source']['path']} for {$entry['package']}.");

        expect(data_get($locked->get($entry['package']), 'dist.type'))
            ->toBe('path', "{$entry['package']} must resolve as a path package per distribution.json.");
    }

    foreach ($manifest->entriesOfKind('vcs') as $entry) {
        expect($repositories->contains(fn ($r) => $r['type'] === 'vcs' && $r['url'] === $entry['source']['url']))
            ->toBeTrue("composer.json needs a vcs repository at {$entry['source']['url']} for {$entry['package']}.");

        $pkg = $locked->get($entry['package']);
        expect(data_get($pkg, 'source.type'))->toBe('git', "{$entry['package']} must resolve from git per distribution.json.");
        expect(data_get($pkg, 'dist.type'))->toBe('zip');
    }

    foreach ($manifest->packageNames() as $i => $package) {
        $constraint = collect($manifest->entries())->firstWhere('package', $package)['constraint'];

        expect(data_get($root, "require.{$package}"))
            ->toBe($constraint, "composer.json's require for {$package} must match distribution.json's constraint.");
    }

    // Folder entries are covered by the root "Plugins\" PSR-4 mapping — the
    // moment that mapping goes, every folder entry must have become a package.
    if ($manifest->entriesOfKind('folder') !== []) {
        expect(data_get($root, 'autoload.psr-4.Plugins\\'))->toBe('plugins/');
    }
})->group('widget-lint');

it('allows no plugin surface the manifest does not declare', function () {
    $manifest = DistributionManifest::load();
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);

    // Every nonprofitcrm/* package composer resolves is a manifest entry.
    $lockedPluginPackages = collect(array_merge($lock['packages'], $lock['packages-dev'] ?? []))
        ->pluck('name')
        ->filter(fn ($name) => str_starts_with($name, 'nonprofitcrm/'))
        ->values();

    expect($lockedPluginPackages->diff($manifest->packageNames())->all())
        ->toBe([], 'composer.lock carries nonprofitcrm/* packages missing from distribution.json.');

    // Every directory under plugins/ is a declared folder or path entry.
    $declaredDirs = collect($manifest->entries())
        ->map(fn ($entry) => $entry['source']['path'] ?? null)
        ->filter();

    foreach (glob(base_path('plugins/*'), GLOB_ONLYDIR) as $dir) {
        $relative = 'plugins/'.basename($dir);

        expect($declaredDirs->contains($relative))
            ->toBeTrue("{$relative} exists on disk but distribution.json does not declare it.");
    }

    // The Dockerfile's plugin-manifest COPY lines (the manifests-before-source
    // layer rule) exist exactly for the manifest's path packages: one line per
    // package per composer stage, and none for anything else.
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
    preg_match_all('#^COPY (plugins/[^/]+/composer\.json) .*$#m', $dockerfile, $matches);
    $copyCounts = array_count_values($matches[1]);

    $expectedCopies = collect($manifest->entriesOfKind('path'))
        ->mapWithKeys(fn ($entry) => [$entry['source']['path'].'/composer.json' => 2])
        ->all();

    expect($copyCounts)->toBe(
        $expectedCopies,
        'Dockerfile plugin-manifest COPY lines must be exactly one per path-sourced manifest entry per composer stage.'
    );

    // Every plugins/ phpunit testsuite belongs to a declared plugin.
    $phpunit = (string) file_get_contents(base_path('phpunit.xml'));
    preg_match_all('#<directory>(plugins/[^/<]+)[^<]*</directory>#', $phpunit, $suites);

    foreach ($suites[1] as $suiteDir) {
        expect($declaredDirs->contains($suiteDir))
            ->toBeTrue("phpunit.xml testsuite under {$suiteDir} has no distribution.json entry.");
    }
})->group('widget-lint');
