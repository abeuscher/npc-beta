<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Payments module boundary guard (session 380, Plugin Architecture arc P4).
 * The Stripe rails live in the nonprofitcrm/payments package (its own repo
 * since session 387, arc D1); core talks to them only through
 * the core-owned seams — CapabilityRegistry::enabled('payments'), the
 * CheckoutProvider contract, and PaymentMode. Three reaches are banned in
 * app/, each with ZERO allowlist:
 *
 *   1. The Stripe SDK (Stripe\ namespace usage, StripeClient).
 *   2. The plugin namespace (Plugins\Payments\...) — verticals resolve core
 *      contracts, never plugin classes.
 *   3. config('services.stripe.*') reads — the capability API is the only
 *      sanctioned "is payments configured?" question. (The session-380 survey
 *      expected SetupChecklist to survive here; it reads the SiteSetting rows
 *      directly, so the enumerated-survivor list is empty.)
 *
 * The client-billing (two-Stripes) bans live in ConventionDriftTest and are
 * untouched by this guard.
 */

/*
 * The plugin-namespace containment check that used to live here now runs
 * generically for every installed plugin in PluginConformanceTest. What stays
 * below is genuinely specific to this vertical: Payments is the only plugin
 * carrying a third-party SDK, so the SDK and config-namespace bans have no
 * generic form and would need an exception list in the kit — which is the
 * kit's rule for what does NOT belong in it.
 */

/** @return array<int, string> every .php file under app/, incl. Blade templates */
function paymentBoundaryAppFiles(): array
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
function paymentBoundaryOffenders(string $pattern): array
{
    $offenders = [];
    foreach (paymentBoundaryAppFiles() as $file) {
        if (preg_match($pattern, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }
    sort($offenders);

    return $offenders;
}

it('keeps the Stripe SDK out of core', function () {
    // Stripe\ followed by an identifier character = namespace usage; prose
    // like "Stripe's" in UI copy does not match.
    $sdkNamespace = '/Stripe\\\\[A-Za-z]/';
    $client       = '/(?<![A-Za-z0-9_])StripeClient/';

    expect(paymentBoundaryOffenders($sdkNamespace))->toBe([])
        ->and(paymentBoundaryOffenders($client))->toBe([]);

    // Positive controls: the plugin's own files DO match both patterns.
    $service = (string) file_get_contents(base_path('vendor/nonprofitcrm/payments/Services/StripeCheckoutService.php'));
    expect($service)->toMatch($sdkNamespace)->toMatch($client);
});

it('keeps services.stripe config reads out of core', function () {
    $pattern = '/config\([\'"]services\.stripe\./';

    expect(paymentBoundaryOffenders($pattern))->toBe([]);

    // Positive control: the plugin's webhook controller DOES read its own
    // config namespace.
    expect((string) file_get_contents(base_path('vendor/nonprofitcrm/payments/Http/Controllers/StripeWebhookController.php')))
        ->toMatch($pattern);
});
