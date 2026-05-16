<?php

use Tests\TestCase;

uses(TestCase::class);

// Pre-Beta pseudo-versioning for FM-driven per-client upgrades.
// Hard requirements: a concrete, immutable, ordered 0.X.Y string, baked into
// the image at build time and read at runtime (never derived from git).

it('ships a VERSION file containing a pre-1.0 0.X.Y string', function () {
    $path = base_path('VERSION');

    expect(file_exists($path))->toBeTrue('VERSION file missing at repository root');

    $version = trim((string) file_get_contents($path));

    expect($version)->toMatch('/^0\.\d+\.\d+$/');
});

it('bakes the version into the image at build time via the APP_VERSION build arg', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)
        ->toContain('ARG APP_VERSION')
        ->toContain('echo "$APP_VERSION" > /var/cache/app/VERSION');
});

it('reads the baked version at runtime from the image file, not from git', function () {
    $config = file_get_contents(base_path('config/fleet.php'));

    expect($config)
        ->toContain("is_readable('/var/cache/app/VERSION')")
        ->not->toContain('git');
});

it('publishes the GHCR image under the validated version tag with an immutable-tag guard', function () {
    $workflow = file_get_contents(base_path('.github/workflows/deploy.yml'));

    // Version is resolved + format-validated from the VERSION file.
    expect($workflow)
        ->toContain('VERSION')
        ->toContain('^0\.[0-9]+\.[0-9]+$')
        // Stamped into the image and used as the published tag, latest still moves.
        ->toContain('APP_VERSION=${{ steps.tag.outputs.IMAGE_TAG }}')
        ->toContain('nonprofitcrm-app:${{ steps.tag.outputs.IMAGE_TAG }}')
        ->toContain('nonprofitcrm-app:latest')
        // Immutable: a re-used version tag fails the build instead of overwriting.
        ->toContain('docker manifest inspect')
        ->toContain('Version tags are immutable');
});
