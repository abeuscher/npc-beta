<?php

use App\Plugins\DistributionManifest;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Plugin package guard (session 382, Plugin Architecture arc P6 — Stage B).
 * config/plugins.php is the sole activation + ordering authority for plugins,
 * packaged or not (contract surface 1, amended posture). A packaged plugin
 * that declared Laravel auto-discovery providers would boot outside the config
 * list and silently break the remove-the-line guarantee — for LogoGarden, the
 * one plugin without a removal-mirror test, nothing else would catch it.
 *
 *   1. No plugin composer.json declares extra.laravel.providers — in-repo
 *      or extracted (Stage C-lite, session 385).
 *   2. Every in-repo plugin composer.json is resolved in composer.lock as a
 *      pinned path package (Stage B: the lockfile carries the package).
 *   3. Every extracted plugin package is lock-pinned to a tagged VCS release
 *      (git source at an exact commit, dist zip, tag-shaped version, no
 *      version field in its manifest).
 *   4. The root "Plugins\" PSR-4 mapping stays until it retires per-plugin
 *      as each becomes a package (contract: accepted overlap, decision 3).
 *
 * The extracted set derives from distribution.json's vcs-sourced entries
 * (session 386, arc P10) — the former hand-maintained EXTRACTED_PLUGIN_PACKAGES
 * const, folded into the manifest so an extraction is declared once.
 */
function extractedPluginPackageNames(): array
{
    return DistributionManifest::load()->packageNamesOfKind('vcs');
}

it('packaged plugins declare no Laravel auto-discovery providers', function () {
    // The in-repo set may be empty (session 388: the Events extraction emptied
    // plugins/ until the next vertical carves into it) — the combined in-repo +
    // extracted set is what must never be empty.
    $manifests = glob(base_path('plugins/*/composer.json'));

    foreach (extractedPluginPackageNames() as $name) {
        $manifests[] = base_path("vendor/{$name}/composer.json");
    }

    expect($manifests)->not->toBeEmpty();

    foreach ($manifests as $manifest) {
        expect(file_exists($manifest))->toBeTrue("Missing plugin manifest: {$manifest}");
        $json = json_decode(file_get_contents($manifest), true);

        expect(data_get($json, 'extra.laravel.providers'))
            ->toBeNull("Auto-discovery providers declared in {$manifest} — activation belongs to config/plugins.php alone.");
    }
})->group('widget-lint');

it('every in-repo plugin package manifest is pinned in composer.lock as a path package', function () {
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
    $locked = collect($lock['packages'])->keyBy('name');

    // The in-repo set may be empty (session 388: the Events extraction emptied
    // plugins/ until the next vertical carves into it) — the shape is asserted
    // for whatever exists.
    $manifests = glob(base_path('plugins/*/composer.json')) ?: [];
    expect($manifests)->toBeArray();

    foreach ($manifests as $manifest) {
        $name = json_decode(file_get_contents($manifest), true)['name'] ?? null;

        expect($name)->not->toBeNull("Missing package name in {$manifest}");
        expect($locked->has($name))->toBeTrue("{$name} is not in composer.lock");
        expect(data_get($locked->get($name), 'dist.type'))->toBe('path');
    }
})->group('widget-lint');

it('every extracted plugin package is lock-pinned to a tagged VCS release', function () {
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
    $locked = collect($lock['packages'])->keyBy('name');

    expect(extractedPluginPackageNames())->not->toBeEmpty();

    foreach (extractedPluginPackageNames() as $name) {
        $pkg = $locked->get($name);

        expect($pkg)->not->toBeNull("{$name} is not in composer.lock");
        // The lockfile records a git source pinned to an exact commit and a
        // dist zip of that commit — never a path back into this repo.
        expect(data_get($pkg, 'source.type'))->toBe('git');
        expect(data_get($pkg, 'source.reference'))->toMatch('/^[0-9a-f]{40}$/');
        expect(data_get($pkg, 'dist.type'))->toBe('zip');
        // Tags are the version metadata (a dev-branch pin would float).
        expect(data_get($pkg, 'version'))->toMatch('/^v\d+\.\d+\.\d+$/');

        // The extracted manifest declares NO version field: under a VCS
        // repository the tag is canonical, and a retained field that
        // disagrees with the tag is a composer install error (the P6
        // path-repo determinism rationale, inverted at P9).
        $manifest = json_decode(file_get_contents(base_path("vendor/{$name}/composer.json")), true);
        expect($manifest)->not->toHaveKey('version', "vendor/{$name}/composer.json must not declare a version field — the git tag is the version.");
    }
})->group('widget-lint');

it('the root composer.json keeps the Plugins PSR-4 mapping while unpackaged plugins remain', function () {
    $root = json_decode(file_get_contents(base_path('composer.json')), true);

    expect(data_get($root, 'autoload.psr-4.Plugins\\'))->toBe('plugins/');
})->group('widget-lint');

it('keeps plugin-owned tables out of the core schema dump', function () {
    // The P7 squash-boundary redraw (session 383, contract surface 5): the
    // core dump covers core tables only; a plugin's tables are created by its
    // own database/migrations/. A regenerated dump that re-absorbs a plugin
    // table would silently re-couple every composition to that plugin's
    // schema — run schema:dump only on a database whose plugin tables were
    // dropped first (see docs/schema/README.md, plugin-owned schema note).
    $dump = file_get_contents(base_path('database/schema/pgsql-schema.sql'));

    $pluginTables = [
        'events'              => 'vendor/nonprofitcrm/events',
        'event_registrations' => 'vendor/nonprofitcrm/events',
        'ticket_tiers'        => 'vendor/nonprofitcrm/events',
        'funds'               => 'vendor/nonprofitcrm/donations',
        'donations'           => 'vendor/nonprofitcrm/donations',
        'donation_credits'    => 'vendor/nonprofitcrm/donations',
        'donation_receipts'   => 'vendor/nonprofitcrm/donations',
    ];

    foreach ($pluginTables as $table => $owner) {
        expect(str_contains($dump, "CREATE TABLE public.{$table} ("))
            ->toBeFalse("{$table} is {$owner}-owned and must not appear in the core dump.");

        expect(glob(base_path("{$owner}/database/migrations/*_create_{$table}_table.php")))
            ->not->toBeEmpty("{$owner} must carry the create-table migration for {$table}.");
    }
})->group('widget-lint');
