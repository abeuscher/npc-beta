<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Perimeter security-header layer (session 370, Security S1). Asserts the header
 * baseline + CSP posture on the public/web surface, the admin panel, and that the
 * Fleet Manager /api/* contract surface is left untouched. Uses the always-present
 * /robots.txt web route as the public probe.
 */

function scriptSrcOf(?string $policy): string
{
    expect($policy)->not->toBeNull();
    preg_match('/script-src ([^;]*)/', (string) $policy, $m);

    return $m[1] ?? '';
}

it('sets the enforced CSP + header baseline on a public web response', function () {
    $res = $this->get('/robots.txt');
    $res->assertOk();

    $res->assertHeader('X-Content-Type-Options', 'nosniff');
    $res->assertHeader('X-Frame-Options', 'DENY');
    $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    expect($res->headers->get('Permissions-Policy'))->not->toBeNull();

    // Public surface is ENFORCED (not Report-Only).
    $csp = $res->headers->get('Content-Security-Policy');
    expect($csp)->not->toBeNull();
    expect($res->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();

    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("object-src 'none'");
    expect($csp)->toContain("frame-ancestors 'none'");
    expect($csp)->toContain("base-uri 'self'");

    // script-src is strict: nonce present, and neither 'unsafe-inline' nor
    // 'unsafe-eval' on the public surface.
    $scriptSrc = scriptSrcOf($csp);
    expect($scriptSrc)->toContain("'nonce-");
    expect($scriptSrc)->not->toContain("'unsafe-inline'");
    expect($scriptSrc)->not->toContain("'unsafe-eval'");

    // style-src carries the accepted 'unsafe-inline' trade-off.
    expect($csp)->toContain("style-src 'self' 'unsafe-inline'");
});

it('does not emit HSTS on a plain-HTTP (dev) request', function () {
    $res = $this->get('http://localhost/robots.txt');

    expect($res->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('emits HSTS on a secure request', function () {
    $res = $this->get('https://localhost/robots.txt');

    expect($res->headers->get('Strict-Transport-Security'))->toContain('max-age=');
});

it('ships the admin panel CSP as Report-Only with unsafe-eval for Alpine', function () {
    $res = $this->get('/admin/login');
    $res->assertOk();

    // Admin surface stages as Report-Only until Filament CSP compat is proven.
    $reportOnly = $res->headers->get('Content-Security-Policy-Report-Only');
    expect($reportOnly)->not->toBeNull();
    expect($res->headers->get('Content-Security-Policy'))->toBeNull();

    $scriptSrc = scriptSrcOf($reportOnly);
    expect($scriptSrc)->toContain("'nonce-");
    expect($scriptSrc)->toContain("'unsafe-eval'");

    // The static header baseline is still enforced on the admin surface.
    $res->assertHeader('X-Frame-Options', 'DENY');
    $res->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('leaves the Fleet Manager /api/* surface untouched — no security headers added', function () {
    $res = $this->get('/api/health');

    expect($res->headers->get('Content-Security-Policy'))->toBeNull();
    expect($res->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
    expect($res->headers->get('X-Frame-Options'))->toBeNull();
    expect($res->headers->get('Referrer-Policy'))->toBeNull();
});

it('honours the per-node CSP allow-list extension (analytics escape valve)', function () {
    config(['security.csp.extra.script_src' => 'https://www.googletagmanager.com']);

    $csp = $this->get('/robots.txt')->headers->get('Content-Security-Policy');

    expect(scriptSrcOf($csp))->toContain('https://www.googletagmanager.com');
});
