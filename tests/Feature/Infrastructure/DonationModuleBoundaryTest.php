<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Donations module boundary guard (session 389, Plugin Architecture arc D3).
 * The Donations vertical lives in plugins/Donations/; the boundary is
 * one-directional — the plugin reaching core (models, Transaction, the
 * capability API, the import wizard concerns) is the allowed hard dependency,
 * but nothing in app/ may reference the plugin namespace, with ZERO
 * allowlist. Core reaches the donations admin surface only through route
 * names (the setup checklist's funds link, the admin tour map), and the
 * models stay core per plan § 6.7.
 *
 * The EventModuleBoundaryTest pattern verbatim.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function donationBoundaryAppFiles(): array
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
function donationBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (donationBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\Donations namespace out of core', function () {
    $pattern = '/Plugins\\\\Donations/';

    expect(donationBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
