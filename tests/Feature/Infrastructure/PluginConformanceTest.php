<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * The plugin conformance kit.
 *
 * One suite that every installed plugin must pass, derived from the
 * distribution manifest rather than written per plugin. Adding a vertical to
 * the manifest brings it under these checks on the same commit — nobody has to
 * remember to write a guard for it, and nobody can ship a plugin that quietly
 * has none.
 *
 * This replaces seven near-identical per-plugin boundary files. They asserted
 * the same thing with a different namespace string each time, so the guard's
 * cost grew with every extraction while its coverage stayed flat. Checks that
 * are genuinely specific to one plugin still belong in that plugin's own file
 * (see PaymentModuleBoundaryTest, which keeps its Stripe-specific bans).
 *
 * Rules for adding to this kit, so it does not rot the way the per-plugin
 * files did:
 *
 *   1. A check earns its place here only if it is true of EVERY plugin. A
 *      check that needs an exception list is a per-plugin check wearing a
 *      generic hat — leave it in the plugin's own file.
 *   2. Do not restate a guard that already exists elsewhere. The manifest's
 *      own consistency (providers present, generated config in sync, composer
 *      and Dockerfile pinned) is DistributionManifestGuardTest's job.
 *   3. The failure message must name the offending plugin, not just the
 *      offending line. A generic suite that fails without saying which plugin
 *      broke is worse than seven bespoke ones.
 *
 * See docs/adr/0009-plugin-tests-in-three-tiers.md for why the kit exists and
 * what belongs in which tier.
 *
 * ── What core legitimately keeps for each vertical ──────────────────────────
 *
 * Carried over from the seven files this replaces. None of it is asserted
 * here — it is the reason core code may mention a vertical's *concepts*
 * without naming its *classes*, which is the line this guard actually draws.
 * Core reaches every plugin's admin surface by route name only.
 *
 *   Blog          the 'post' page type, its engine consumers and the blog
 *                 prefix plumbing are core CMS vocabulary, not plugin reaches
 *   Donations     the models and Transaction stay core
 *   Events        the models stay core; so does the landing-page factory
 *   Forms         Form, FormSubmission, their factories and the seeder stay
 *                 core, composition-gated on route presence
 *   MemberPortal  PortalAccount and its two mailables stay core
 *   Memberships   the models and Transaction stay core
 *   Payments      reached only through the capability layer; its Stripe-
 *                 specific bans stay in PaymentModuleBoundaryTest
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function conformanceCoreFiles(): array
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

/** @return array<int, string> repo-relative paths of core files matching $pattern */
function conformanceOffenders(string $pattern): array
{
    $offenders = [];
    foreach (conformanceCoreFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

/**
 * The installed plugin set, read from the distribution manifest — the same
 * authority config/plugins.php is generated from. Keyed by package name so a
 * failure names the plugin.
 *
 * @return array<string, string> package name => root namespace (e.g. "Plugins\Blog")
 */
function conformancePluginNamespaces(): array
{
    $manifest = json_decode((string) file_get_contents(base_path('distribution.json')), true);

    $namespaces = [];
    foreach ($manifest['plugins'] ?? [] as $plugin) {
        $segments = explode('\\', (string) $plugin['provider']);
        $namespaces[(string) $plugin['package']] = $segments[0] . '\\' . $segments[1];
    }

    return $namespaces;
}

it('has a non-empty plugin set to check', function () {
    // Guards the guard: if the manifest ever fails to parse, or its shape
    // changes, every check below would vacuously pass against an empty list
    // and this file would report green while asserting nothing.
    expect(conformancePluginNamespaces())->not->toBeEmpty();
});

it('keeps every installed plugin namespace out of core', function () {
    // The boundary is one-directional. A plugin reaching core is the allowed
    // hard dependency; core reaching a plugin is not, with zero allowlist.
    // Core touches a plugin's surface only through the published seams —
    // route names, events, the capability layer — never by naming its classes.
    $violations = [];
    $unwired    = [];
    $wiring     = (string) file_get_contents(config_path('plugins.php'));

    foreach (conformancePluginNamespaces() as $package => $namespace) {
        $pattern   = '/' . preg_quote($namespace, '/') . '\b/';
        $offenders = conformanceOffenders($pattern);

        if ($offenders !== []) {
            $violations[$package] = $offenders;
        }

        // Positive control, per plugin: the wiring point does match, and is
        // deliberately outside the scanned set. Without it the check would
        // still pass if a pattern were silently wrong — the scan would find
        // nothing because it was looking for nothing.
        if (preg_match($pattern, $wiring) !== 1) {
            $unwired[] = $package;
        }
    }

    // Reported separately: an empty $violations means "core is clean", but an
    // empty $violations with a non-empty $unwired means "the scan was blind".
    expect($unwired)->toBe([]);
    expect($violations)->toBe([]);
});
