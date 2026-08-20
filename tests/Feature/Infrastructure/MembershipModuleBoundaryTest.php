<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Memberships module boundary guard (session 392, Plugin Architecture arc D4).
 * The Memberships vertical lives in its own repository (crm-plugin--memberships,
 * extracted session 393, resolving into vendor/nonprofitcrm/memberships/); the boundary is
 * one-directional — the plugin reaching core (models, Transaction, the
 * capability API, the import wizard concerns) is the allowed hard dependency,
 * but nothing in app/ may reference the plugin namespace, with ZERO
 * allowlist. Core reaches the memberships admin surface only through route
 * names (the admin tour map), and the models stay core per plan § 6.7.
 *
 * The DonationModuleBoundaryTest pattern verbatim.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function membershipBoundaryAppFiles(): array
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
function membershipBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (membershipBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\Memberships namespace out of core', function () {
    $pattern = '/Plugins\\\\Memberships/';

    expect(membershipBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
