<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Forms module boundary guard (session 397, Plugin Architecture arc D7). The
 * forms vertical lives in its own repository (crm-plugin--forms, extracted
 * session 397), consumed as vendor/nonprofitcrm/forms; the boundary is
 * one-directional —
 * the plugin reaching core (Form, FormSubmission, Contact, Note, PiiScanner,
 * EmailTemplate, the capability registry) is the allowed hard dependency,
 * but nothing in app/ may reference the plugin namespace, with ZERO
 * allowlist. Core reaches the forms admin surface only by route name
 * (QuickActions' new_form entry, the admin-tour URL map); the Form and
 * FormSubmission models, their factories, and FormSeeder stay core (§ 6.7),
 * composition-gated on route presence.
 *
 * The BlogModuleBoundaryTest pattern verbatim.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function formsBoundaryAppFiles(): array
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
function formsBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (formsBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\Forms namespace out of core', function () {
    $pattern = '/Plugins\\\\Forms\b/';

    expect(formsBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
