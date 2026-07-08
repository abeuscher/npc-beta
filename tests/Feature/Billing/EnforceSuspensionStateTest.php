<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Middleware matrix for EnforceSuspensionState (client billing, contract v2.6.0).
|
| Surfaces exercised:
|   - admin panel page          GET /admin
|   - admin login               GET /admin/login
|   - in-panel API group        GET /admin/api/page-builder/widget-types
|   - public page               GET /
|   - donation checkout         POST donations.checkout
|   - member portal             GET /system/login
|   - every FM contract endpoint GET /api/health (representative — same api group)
|
| The admin-locked notice carries data-suspension-notice="admin-locked"; the
| site-off notice carries data-suspension-notice="site-off". "Stays up" = the
| response is not one of those notices.
*/

const ADMIN_LOCKED_MARKER = 'data-suspension-notice="admin-locked"';
const SITE_OFF_MARKER = 'data-suspension-notice="site-off"';

// ── State: none / absent — additive by construction, every install unaffected ─

it('leaves every surface untouched when the flag is none (absent default)', function () {
    config(['fleet.suspension.state' => 'none']);

    // Admin surfaces are not the suspension notice (unauthenticated → auth
    // redirect, not a lock screen).
    $this->get('/admin')->assertDontSee(ADMIN_LOCKED_MARKER, false);
    $this->get('/admin/login')->assertDontSee(ADMIN_LOCKED_MARKER, false);
    $this->get('/admin/api/page-builder/widget-types')->assertDontSee(ADMIN_LOCKED_MARKER, false);

    // A reliably-200 public route is served normally, not the maintenance notice.
    $this->get('/robots.txt')->assertStatus(200)->assertDontSee(SITE_OFF_MARKER, false);

    // FM endpoints respond normally.
    $this->getJson('/api/health')->assertStatus(200);
});

// ── State: admin_locked — back office locked, constituents untouched ──────────

it('locks the admin panel with the suspension notice under admin_locked', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    $this->get('/admin')
        ->assertStatus(403)
        ->assertSee(ADMIN_LOCKED_MARKER, false);
});

it('locks the admin login behind the suspension notice under admin_locked', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    $this->get('/admin/login')
        ->assertStatus(403)
        ->assertSee(ADMIN_LOCKED_MARKER, false);
});

it('locks the in-panel API routes under admin_locked', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    // The lock fires before Authenticate, so an unauthenticated in-panel API call
    // gets the notice, not an auth redirect — proving the single panel-base
    // registration covers the ->routes() API groups.
    $this->get('/admin/api/page-builder/widget-types')
        ->assertStatus(403)
        ->assertSee(ADMIN_LOCKED_MARKER, false);
});

it('keeps the public site up under admin_locked', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    $response = $this->get('/');

    $response->assertDontSee(ADMIN_LOCKED_MARKER, false)
        ->assertDontSee(SITE_OFF_MARKER, false);
    expect($response->status())->not->toBe(403)->not->toBe(503);
});

it('keeps donation checkout up under admin_locked (the org’s own Stripe)', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    $response = $this->post(route('donations.checkout'), []);

    // Whatever the controller does with an empty body, it is not the lock screen
    // and not a 503.
    $response->assertDontSee(ADMIN_LOCKED_MARKER, false);
    expect($response->status())->not->toBe(503);
});

it('keeps the member portal up under admin_locked (the lock punishes the back office, never constituents)', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    // The suspension gate passes the portal through to its own controller —
    // whatever that returns, it is not the admin lock screen and not a 503.
    $response = $this->get('/system/login');

    $response->assertDontSee(ADMIN_LOCKED_MARKER, false)
        ->assertDontSee(SITE_OFF_MARKER, false);
    expect($response->status())->not->toBe(403)->not->toBe(503);
});

it('keeps the FM contract endpoints up under admin_locked', function () {
    config(['fleet.suspension.state' => 'admin_locked']);

    $this->getJson('/api/health')
        ->assertStatus(200)
        ->assertJsonPath('subchecks.suspension.value.state', 'admin_locked');
});

// ── State: site_off — manual nuclear shutoff, only FM monitoring survives ─────

it('renders the public maintenance notice (503) under site_off', function () {
    config(['fleet.suspension.state' => 'site_off']);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee(SITE_OFF_MARKER, false);
});

it('takes the member portal down under site_off', function () {
    config(['fleet.suspension.state' => 'site_off']);

    $this->get('/system/login')
        ->assertStatus(503)
        ->assertSee(SITE_OFF_MARKER, false);
});

it('takes the admin panel down under site_off with the maintenance notice', function () {
    config(['fleet.suspension.state' => 'site_off']);

    $this->get('/admin')
        ->assertStatus(503)
        ->assertSee(SITE_OFF_MARKER, false);
});

it('keeps the FM contract endpoints up under site_off — a shut-off node is still monitored', function () {
    config(['fleet.suspension.state' => 'site_off']);

    $this->getJson('/api/health')
        ->assertStatus(200)
        ->assertJsonPath('subchecks.suspension.value.state', 'site_off');
});

// ── State: unrecognized — fail safe to none (never brick a paying admin) ──────

it('fails an unrecognized flag value safe — the admin panel stays reachable', function () {
    config(['fleet.suspension.state' => 'garbled_value']);

    $response = $this->get('/admin');

    $response->assertDontSee(ADMIN_LOCKED_MARKER, false)
        ->assertDontSee(SITE_OFF_MARKER, false);
    expect($response->status())->not->toBe(403)->not->toBe(503);

    $this->get('/')->assertDontSee(SITE_OFF_MARKER, false);
});

// ── Lock-screen copy — reason-appropriate, self-cure affordances from the doc ─

it('renders reason-appropriate copy and self-cure affordances from the pushed document', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fleet/billing-state.json', json_encode([
        'schema_version' => 1,
        'as_of' => '2026-07-08T12:00:00+00:00',
        'billing_contact_email' => 'billing@example.org',
        'portal_url' => 'https://billing.stripe.com/p/session/test_123',
        'suspension' => ['state' => 'admin_locked', 'reason' => 'delinquent'],
    ]));
    config(['fleet.suspension.state' => 'admin_locked']);

    $response = $this->get('/admin');

    $response->assertStatus(403)
        ->assertSee('billing issue has paused admin access', false) // delinquent copy
        ->assertSee('https://billing.stripe.com/p/session/test_123', false) // portal link
        ->assertSee('billing@example.org', false); // billing contact
});

it('locks with generic copy and no affordances when no document is present', function () {
    Storage::fake('local'); // no billing-state.json
    config(['fleet.suspension.state' => 'admin_locked']);

    $response = $this->get('/admin');

    // Enforcement rides the flag: the node still locks with no document, using
    // generic copy and rendering no portal/contact affordances.
    $response->assertStatus(403)
        ->assertSee(ADMIN_LOCKED_MARKER, false)
        ->assertDontSee('Open the billing portal', false)
        ->assertDontSee('Billing contact on file', false);
});
