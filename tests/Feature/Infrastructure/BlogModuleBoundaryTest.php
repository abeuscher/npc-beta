<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Blog module boundary guard (session 396, Plugin Architecture arc D6). The
 * blog vertical lives in plugins/Blog (external repo from the D6 extraction);
 * the boundary is one-directional — the plugin reaching core (Page, the page
 * renderer, the export services, HasPageBuilderForm) is the allowed hard
 * dependency, but nothing in app/ may reference the plugin namespace, with
 * ZERO allowlist. Core reaches the blog admin surface only by route name
 * (QuickActions' new_post entry, PageBuilder's details URL); the 'post' page
 * type, its engine consumers, and the blog_prefix plumbing are core CMS
 * vocabulary, not plugin reaches.
 *
 * The MemberPortalModuleBoundaryTest pattern verbatim.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function blogBoundaryAppFiles(): array
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
function blogBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (blogBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Plugins\\Blog namespace out of core', function () {
    $pattern = '/Plugins\\\\Blog\b/';

    expect(blogBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the wiring point (config/plugins.php) DOES match and
    // is deliberately outside the scanned set.
    expect((string) file_get_contents(config_path('plugins.php')))->toMatch($pattern);
});
