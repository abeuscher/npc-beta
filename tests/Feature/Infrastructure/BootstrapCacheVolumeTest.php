<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the two halves of the 0.345.06 deploy-brick fix so they can't silently
 * regress:
 *
 *  1. bootstrap/cache must never be a persistent volume in prod compose. It
 *     holds only derived, image-specific files (packages.php/services.php);
 *     persisting it let a manifest from a pre-image survive an image swap and
 *     reference a removed provider (laravel-debugbar), fataling at bootstrap.
 *  2. The app image must declare a HEALTHCHECK so `compose up --wait` waits for
 *     a real framework boot instead of false-greening past a non-booting image.
 */
it('does not mount bootstrap/cache as a persistent volume in prod compose', function () {
    $compose = file_get_contents(base_path('docker-compose.prod.yml'));

    // Match a volume mount *target* (`- <vol>:/var/www/html/bootstrap/cache`),
    // not prose — the explanatory comments in the compose file mention the path
    // deliberately and must not trip the guard.
    $this->assertStringNotContainsString(
        ':/var/www/html/bootstrap/cache',
        $compose,
        'docker-compose.prod.yml must not mount bootstrap/cache — it holds only '
        . 'derived, image-specific files and a stale manifest can shadow a new '
        . "image's vendor (the 0.345.06 Debugbar deploy brick). The image bakes "
        . 'and chowns the directory, so no volume is needed.'
    );
});

it('declares a HEALTHCHECK on the app image so a non-booting image fails up --wait', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    $this->assertStringContainsString(
        'HEALTHCHECK',
        $dockerfile,
        'The Dockerfile must declare a HEALTHCHECK that boots the framework '
        . '(php artisan app:health-check) so a bootstrap-level fatal trips the '
        . 'check and `compose up --wait` catches a bad image at docker_up.'
    );

    $this->assertStringContainsString(
        'app:health-check',
        $dockerfile,
        'The HEALTHCHECK must invoke the app:health-check artisan command, which '
        . 'boots the full framework (so a stale-manifest bootstrap fatal is caught).'
    );
});
