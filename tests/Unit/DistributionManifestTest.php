<?php

use App\Plugins\DistributionManifest;
use Tests\TestCase;

uses(TestCase::class);

// Session 386 (Plugin Architecture arc P10): unit coverage for the manifest
// reader's fail-loud validation — a malformed distribution.json must break
// the build (the guard test and plugins:manifest-sync both load through
// here), never silently compose the wrong plugin set. Same posture as the
// unknown-PLUGINS_DISABLED-handle rule (contract surface 1).

function manifestJson(array $plugins): string
{
    return json_encode(['name' => 'nonprofitcrm/test', 'plugins' => $plugins]);
}

const VALID_VCS_ENTRY = [
    'provider' => 'Plugins\LogoGarden\LogoGardenServiceProvider',
    'package' => 'nonprofitcrm/logo-garden',
    'constraint' => '^0.1',
    'source' => ['type' => 'vcs', 'url' => 'https://github.com/abeuscher/crm-plugin--logo-garden.git'],
];

const VALID_FOLDER_ENTRY = [
    'provider' => 'Plugins\Payments\PaymentsServiceProvider',
    'source' => ['type' => 'folder', 'path' => 'plugins/Payments'],
];

it('parses a valid manifest preserving entry order as provider load order', function () {
    $manifest = DistributionManifest::fromJson(manifestJson([VALID_VCS_ENTRY, VALID_FOLDER_ENTRY]));

    expect($manifest->providers())->toBe([
        'Plugins\LogoGarden\LogoGardenServiceProvider',
        'Plugins\Payments\PaymentsServiceProvider',
    ])
        ->and($manifest->packageNames())->toBe(['nonprofitcrm/logo-garden'])
        ->and($manifest->packageNamesOfKind('vcs'))->toBe(['nonprofitcrm/logo-garden'])
        ->and($manifest->entriesOfKind('folder'))->toHaveCount(1);
});

it('generates the config list from the manifest order', function () {
    $generated = DistributionManifest::fromJson(manifestJson([VALID_VCS_ENTRY, VALID_FOLDER_ENTRY]))->generatedConfig();

    expect($generated)->toContain("return [\n    Plugins\LogoGarden\LogoGardenServiceProvider::class,\n    Plugins\Payments\PaymentsServiceProvider::class,\n];\n")
        ->and($generated)->toStartWith("<?php\n")
        ->and($generated)->toContain('GENERATED FILE');
});

it('rejects invalid JSON', function () {
    DistributionManifest::fromJson('{nope');
})->throws(RuntimeException::class, 'not valid JSON');

it('rejects a manifest without a plugins array', function () {
    DistributionManifest::fromJson(json_encode(['name' => 'x', 'plugins' => []]));
})->throws(RuntimeException::class, 'non-empty "plugins" array');

it('rejects an entry missing its provider', function () {
    DistributionManifest::fromJson(manifestJson([['source' => ['type' => 'folder', 'path' => 'plugins/X']]]));
})->throws(RuntimeException::class, 'must declare a provider FQCN');

it('rejects an unknown source kind, naming the valid set', function () {
    $entry = VALID_FOLDER_ENTRY;
    $entry['source']['type'] = 'submodule';

    DistributionManifest::fromJson(manifestJson([$entry]));
})->throws(RuntimeException::class, "source.type 'submodule'; valid kinds: folder, path, vcs");

it('rejects a vcs entry without a package constraint', function () {
    $entry = VALID_VCS_ENTRY;
    unset($entry['constraint']);

    DistributionManifest::fromJson(manifestJson([$entry]));
})->throws(RuntimeException::class, "requires 'constraint'");

it('rejects a path entry without a source path', function () {
    DistributionManifest::fromJson(manifestJson([[
        'provider' => 'Plugins\Events\EventsServiceProvider',
        'package' => 'nonprofitcrm/events',
        'constraint' => '^0.1',
        'source' => ['type' => 'path'],
    ]]));
})->throws(RuntimeException::class, 'requires source.path');

it('rejects a folder entry that claims a composer package', function () {
    $entry = VALID_FOLDER_ENTRY;
    $entry['package'] = 'nonprofitcrm/payments';

    DistributionManifest::fromJson(manifestJson([$entry]));
})->throws(RuntimeException::class, 'must not declare');

it('rejects duplicate providers', function () {
    DistributionManifest::fromJson(manifestJson([VALID_FOLDER_ENTRY, VALID_FOLDER_ENTRY]));
})->throws(RuntimeException::class, 'duplicate provider');
