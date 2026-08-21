<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Products module boundary guard (session 398, Plugin Architecture arc D8).
 * The products vertical lives at plugins/Products/ (its extraction to
 * crm-plugin--products is this block's closer); the boundary is
 * one-directional — the plugin reaching core (Product, ProductPrice,
 * Purchase, WaitlistEntry, Contact, Transaction, ActivityLogger, the
 * capability registry, the payments contracts) is the allowed hard
 * dependency, but nothing in app/ may reference the plugin namespace, with
 * ZERO allowlist. Core reaches the products surface only by route name (the
 * composition-safety gates); the four product models, their factories, and
 * the products system-collection seeding stay core (§ 6.7), gated on route
 * presence.
 *
 * The FormsModuleBoundaryTest pattern verbatim.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function productsBoundaryAppFiles(): array
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
function productsBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (productsBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\Products namespace out of core', function () {
    $pattern = '/Plugins\\\\Products\b/';

    expect(productsBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
