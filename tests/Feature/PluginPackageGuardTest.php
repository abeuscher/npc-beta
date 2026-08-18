<?php

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
 *   1. No plugin composer.json declares extra.laravel.providers.
 *   2. Every plugin composer.json is resolved in composer.lock as a pinned
 *      path package (Stage B: the lockfile carries the package).
 *   3. The root "Plugins\" PSR-4 mapping stays until it retires per-plugin
 *      as each becomes a package (contract: accepted overlap, decision 3).
 */
it('packaged plugins declare no Laravel auto-discovery providers', function () {
    $manifests = glob(base_path('plugins/*/composer.json'));

    expect($manifests)->not->toBeEmpty();

    foreach ($manifests as $manifest) {
        $json = json_decode(file_get_contents($manifest), true);

        expect(data_get($json, 'extra.laravel.providers'))
            ->toBeNull("Auto-discovery providers declared in {$manifest} — activation belongs to config/plugins.php alone.");
    }
})->group('widget-lint');

it('every plugin package manifest is pinned in composer.lock as a path package', function () {
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
    $locked = collect($lock['packages'])->keyBy('name');

    foreach (glob(base_path('plugins/*/composer.json')) as $manifest) {
        $name = json_decode(file_get_contents($manifest), true)['name'] ?? null;

        expect($name)->not->toBeNull("Missing package name in {$manifest}");
        expect($locked->has($name))->toBeTrue("{$name} is not in composer.lock");
        expect(data_get($locked->get($name), 'dist.type'))->toBe('path');
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
        'events'              => 'plugins/Events',
        'event_registrations' => 'plugins/Events',
        'ticket_tiers'        => 'plugins/Events',
    ];

    foreach ($pluginTables as $table => $owner) {
        expect(str_contains($dump, "CREATE TABLE public.{$table} ("))
            ->toBeFalse("{$table} is {$owner}-owned and must not appear in the core dump.");

        expect(glob(base_path("{$owner}/database/migrations/*_create_{$table}_table.php")))
            ->not->toBeEmpty("{$owner} must carry the create-table migration for {$table}.");
    }
})->group('widget-lint');
