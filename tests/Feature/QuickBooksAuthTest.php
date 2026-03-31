<?php

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\QuickBooks\QuickBooksAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

function seedQbCredentials(): void
{
    SiteSetting::create([
        'key'   => 'qb_client_id',
        'value' => Crypt::encryptString('test_client_id'),
        'group' => 'finance',
        'type'  => 'encrypted',
    ]);
    SiteSetting::create([
        'key'   => 'qb_client_secret',
        'value' => Crypt::encryptString('test_client_secret'),
        'group' => 'finance',
        'type'  => 'encrypted',
    ]);
    Cache::forget('site_setting:qb_client_id');
    Cache::forget('site_setting:qb_client_secret');
}

// ── Token storage ───────────────────────────────────────────────────────────

it('stores QB tokens as encrypted site settings', function () {
    $auth = new QuickBooksAuth();

    // Simulate storing tokens via the same encrypted pattern exchangeCode uses.
    SiteSetting::create([
        'key'   => 'qb_access_token',
        'value' => Crypt::encryptString('test_access_token'),
        'group' => 'finance',
        'type'  => 'encrypted',
    ]);
    SiteSetting::create([
        'key'   => 'qb_refresh_token',
        'value' => Crypt::encryptString('test_refresh_token'),
        'group' => 'finance',
        'type'  => 'encrypted',
    ]);
    SiteSetting::create([
        'key'   => 'qb_realm_id',
        'value' => Crypt::encryptString('123456789'),
        'group' => 'finance',
        'type'  => 'encrypted',
    ]);
    SiteSetting::create([
        'key'   => 'qb_token_expires_at',
        'value' => Crypt::encryptString(now()->addHour()->toIso8601String()),
        'group' => 'finance',
        'type'  => 'encrypted',
    ]);

    foreach (['qb_access_token', 'qb_refresh_token', 'qb_realm_id', 'qb_token_expires_at'] as $key) {
        Cache::forget("site_setting:{$key}");
    }

    expect($auth->getRealmId())->toBe('123456789');
});

// ── Disconnect ──────────────────────────────────────────────────────────────

it('clears all QB tokens on disconnect', function () {
    foreach (['qb_access_token', 'qb_refresh_token', 'qb_realm_id', 'qb_token_expires_at'] as $key) {
        SiteSetting::create([
            'key'   => $key,
            'value' => Crypt::encryptString('test_value'),
            'group' => 'finance',
            'type'  => 'encrypted',
        ]);
        Cache::forget("site_setting:{$key}");
    }

    $auth = new QuickBooksAuth();
    expect($auth->getRealmId())->not->toBeNull();

    $auth->disconnect();

    foreach (['qb_access_token', 'qb_refresh_token', 'qb_realm_id', 'qb_token_expires_at'] as $key) {
        Cache::forget("site_setting:{$key}");
    }

    expect($auth->getRealmId())->toBeNull();
});

// ── isConnected ─────────────────────────────────────────────────────────────

it('reports not connected when no tokens exist', function () {
    $auth = new QuickBooksAuth();
    $realmId = $auth->getRealmId();
    expect($realmId)->toBeNull();
});

// ── Auth gate on connect route ──────────────────────────────────────────────

it('redirects unauthenticated users from QB connect route', function () {
    $response = $this->get('/admin/quickbooks/connect');
    $response->assertRedirect();
});

it('denies QB connect to users without manage_financial_settings permission', function () {
    seedQbCredentials();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/quickbooks/connect')
        ->assertForbidden();
});

it('allows QB connect for super_admin users', function () {
    seedQbCredentials();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)
        ->get('/admin/quickbooks/connect');

    // Should redirect to Intuit OAuth URL
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('intuit.com');
});

// ── Connect requires credentials ────────────────────────────────────────────

it('redirects back with error when QB credentials are not set', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)
        ->get('/admin/quickbooks/connect');

    $response->assertRedirect(route('filament.admin.pages.finance-settings-page'));
});

// ── Callback CSRF state validation ──────────────────────────────────────────

it('rejects QB callback with missing state parameter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)
        ->get('/admin/quickbooks/callback?code=test_code&realmId=123');

    $response->assertRedirect();
});

it('rejects QB callback with mismatched state parameter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)
        ->withSession(['qb_oauth_state' => 'correct_state'])
        ->get('/admin/quickbooks/callback?code=test_code&realmId=123&state=wrong_state');

    $response->assertRedirect();
});

// ── QB disconnect route auth gate ───────────────────────────────────────────

it('denies QB disconnect to users without permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/admin/quickbooks/disconnect')
        ->assertForbidden();
});
