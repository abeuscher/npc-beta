<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Member Portal module boundary guard (session 394, Plugin Architecture arc
 * D5). The portal vertical lives in its own repository
 * (crm-plugin--member-portal, extracted session 395), consumed as
 * vendor/nonprofitcrm/member-portal; the boundary is one-directional —
 * the plugin reaching core (PortalAccount, Contact, the page renderer, the
 * capability API, core mailables) is the allowed hard dependency, but nothing
 * in app/ may reference the plugin namespace, with ZERO allowlist. Core
 * reaches the portal only through the member-portal capability and route
 * names; PortalAccount and its two mailables stay core per plan § 6.7.
 *
 * The MembershipModuleBoundaryTest pattern verbatim.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function memberPortalBoundaryAppFiles(): array
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
function memberPortalBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (memberPortalBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\MemberPortal namespace out of core', function () {
    $pattern = '/Plugins\\\\MemberPortal/';

    expect(memberPortalBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
