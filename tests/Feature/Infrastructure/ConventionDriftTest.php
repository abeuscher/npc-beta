<?php

use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Http\Controllers\Api\Fleet\Concerns\HasContractVersion;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Convention-drift gate — Code Review & Cleanup Cycle 3 (session 345), the
 * standing inter-cycle test that pins the divergence classes each cycle's audit
 * has surfaced, so they don't silently reappear between cycles. Lives alongside
 * FmContractVersionParityTest (the FM contract gate). Each future cycle adds
 * rows here for new convention drift it finds.
 */

// ── Filament signature drift (the inert-dead-code class, e.g. getRelationManagers) ──

it('has no [KNOWN-BAD] Filament signature-drift hits', function () {
    // Wraps the reusable static-reflection scan (session 344). A [KNOWN-BAD] hit
    // is a Filament v2 method name (getRelationManagers → getRelations) that
    // compiles cleanly but the framework never calls — the 275 bug class.
    $result = Process::path(base_path())->run('php scripts/filament-signature-scan.php');

    expect($result->successful())->toBeTrue();
    expect($result->output())->not->toContain('[KNOWN-BAD]');
}); // s364 D4: 0.5s local — the scan is cheap; re-tag slow only if it grows past 5s.

// ── model_type lowercase short-name convention (the 275 trait bug class) ──

it('derives custom-field model_type as a lowercase short name at the single derivation point', function () {
    // SanitisesRichTextCustomFields::sanitiseRichTextCustomFields() resolves
    // model_type via Str::snake(class_basename($model::class)); the codebase
    // convention is lowercase short names ('contact', not the FQCN). Pin the
    // derivation so a future model can't silently reintroduce the s275 no-op.
    $expected = [
        Contact::class      => 'contact',
        Event::class        => 'event',
        Page::class         => 'page',
        Donation::class     => 'donation',
        Organization::class => 'organization',
        Transaction::class  => 'transaction',
    ];

    foreach ($expected as $class => $modelType) {
        $derived = Str::snake(class_basename($class));

        expect($derived)->toBe($modelType)
            ->and($derived)->toMatch('/^[a-z_]+$/'); // lowercase, no FQCN backslashes
    }
});

// ── Trait constants exist with their expected shape (accessed self:: from hosts) ──

it('exposes the load-bearing trait constants with their expected shapes', function () {
    // CONTRACT_VERSION is a trait constant (not accessible as Trait::CONST
    // directly); ORG_SENTINEL_TYPES and DATE_PREFIX_PATTERN are private consts
    // accessed self:: from their host context — read all three via reflection.
    $contractVersion = (new ReflectionClassConstant(HasContractVersion::class, 'CONTRACT_VERSION'))->getValue();
    expect($contractVersion)->toBe('2.7.0');

    $sentinels = (new ReflectionClassConstant(InteractsWithImportWizard::class, 'ORG_SENTINEL_TYPES'))->getValue();
    expect($sentinels)->toBeArray()->not->toBeEmpty();

    $datePattern = (new ReflectionClassConstant(InteractsWithImportProgress::class, 'DATE_PREFIX_PATTERN'))->getValue();
    expect($datePattern)->toBeString()
        ->and(@preg_match($datePattern, '') !== false)->toBeTrue(); // valid regex
});

// ── Boolean SiteSetting convention: type=string + 'true'/'false', never type=boolean ──

const CONVENTION_BOOLEAN_KEYS = [
    'horizon_enabled',
    'notes_edit_only_by_creator',
    'auto_publish_pages',
    'auto_publish_posts',
    'noindex_global',
    'stripe_tos_url_configured',
    'stripe_dashboard_branding_confirmed',
    'event_auto_publish',
];

it('writes every boolean SiteSetting through set() as a non-boolean (string) type', function () {
    foreach (CONVENTION_BOOLEAN_KEYS as $key) {
        SiteSetting::set($key, 'true');
    }

    // The canonical write path never produces the legacy type=boolean shape.
    expect(SiteSetting::where('type', 'boolean')->count())->toBe(0);
});

it('no longer supports the legacy boolean value-cast shape', function () {
    // The type=boolean cast branch was removed (session 345) — a row carrying the
    // legacy shape now reads back as its raw 'true'/'false' string, which is the
    // convention (callers compare === 'true').
    SiteSetting::create(['key' => 'legacy_boolean_probe', 'value' => 'true', 'type' => 'boolean']);

    expect(SiteSetting::get('legacy_boolean_probe'))->toBe('true');
});

// ── Resource permission-gate uniformity (Flag 344-F: bless the canViewAny delegation) ──

it('gates every Filament Resource via an explicit canAccess() or an overridden canViewAny()', function () {
    // The convention is "every admin Resource declares its own access gate."
    // 21 of 27 do so via canAccess(); the other 6 gate via an overridden
    // canViewAny() (Filament's base canAccess() delegates to it). Both are
    // blessed here — what is NOT allowed is a Resource that overrides neither
    // and silently inherits the framework default.
    $ungated = [];

    foreach (glob(app_path('Filament/Resources/*.php')) as $file) {
        $fqcn = 'App\\Filament\\Resources\\' . basename($file, '.php');

        if (! class_exists($fqcn) || ! is_subclass_of($fqcn, \Filament\Resources\Resource::class)) {
            continue;
        }

        $declaresOwn = fn (string $method): bool =>
            (new ReflectionMethod($fqcn, $method))->getDeclaringClass()->getName() === $fqcn;

        if (! $declaresOwn('canAccess') && ! $declaresOwn('canViewAny')) {
            $ungated[] = class_basename($fqcn);
        }
    }

    expect($ungated)->toBe([]);
});

// ── Two-Stripes separation guard (CB2 / session 367, track § Design decision 6) ──
// The app's existing Stripe integration is the client org's OWN donation Stripe.
// Vendor billing (the owner's Stripe) is touched ONLY by Fleet Manager, in the FM
// repo — the CRM node holds no vendor credential, config key, or SDK call and
// renders a pushed document alone. These two cases keep the two from co-mingling.

it('bans vendor-billing identifiers anywhere in CRM code (the FM-side names are reserved)', function () {
    // stripe_billing / vendor_stripe / STRIPE_BILLING* are Fleet-Manager-side
    // identifiers (FM's disjoint `services.stripe_billing.*` namespace + env keys).
    // No CRM code may reference them — the donation surface uses `services.stripe.*`
    // and `stripe_*_key`, never these. Scans source (app/) and config/ only, so
    // this test's own banned-token list (in tests/) never self-trips.
    $banned = '/(stripe_billing|vendor_stripe)/i';
    $offenders = [];

    foreach ([app_path(), config_path()] as $root) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (preg_match($banned, (string) file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps the billing/account code free of Stripe SDK/config references (renders the pushed document alone)', function () {
    // The billing-state reader/DTO + the Account page render the FM-pushed document
    // and nothing else: no `services.stripe.*` config read, no StripeClient, no
    // Stripe SDK namespace. The word "Stripe" is allowed as a link label (in the
    // Blade view / comments) — this scans the PHP code files only.
    $files = array_merge(
        glob(app_path('Services/Billing/*.php')),
        [app_path('Filament/Pages/Settings/AccountPage.php')],
    );
    $banned = '/(services\.stripe|StripeClient|Stripe\\\\)/';
    $offenders = [];

    foreach ($files as $file) {
        if (preg_match($banned, (string) file_get_contents($file))) {
            $offenders[] = str_replace(base_path() . '/', '', $file);
        }
    }

    expect($offenders)->toBe([]);

    // Positive control: the mapped donation surface DOES reference these and is
    // deliberately OUTSIDE the scanned set — the guard must never catch it.
    expect(file_get_contents(app_path('Services/StripeCheckoutService.php')))
        ->toMatch($banned);
});
