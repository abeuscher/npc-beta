<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Events module boundary guard (session 381, Plugin Architecture arc P5).
 * The Events vertical lives in plugins/Events/; the boundary is
 * one-directional — the plugin reaching core (models, Transaction, the
 * capability API) is the allowed hard dependency, but nothing in app/ may
 * reference the plugin namespace, with ZERO allowlist. Core reaches the
 * events admin surface only through route names (QuickActions, the
 * Organization relation manager, the admin tour map) and the landing-page
 * factory lives in core (App\Services\EventLandingPageFactory) because the
 * models are core at Stage A.
 *
 * The PaymentModuleBoundaryTest pattern minus the SDK bans — this vertical
 * carries no third-party SDK.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function eventBoundaryAppFiles(): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS),
    );
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** @return array<int, string> repo-relative offender paths */
function eventBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (eventBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\Events namespace out of core', function () {
    $pattern = '/Plugins\\\\Events/';

    expect(eventBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
