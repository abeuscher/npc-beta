<?php

use App\Jobs\SyncTransactionToQuickBooks;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

function seedQbSyncConnection(): void
{
    $keys = [
        'qb_access_token'     => 'test_access_token',
        'qb_refresh_token'    => 'test_refresh_token',
        'qb_realm_id'         => '123456789',
        'qb_token_expires_at' => now()->addHour()->toIso8601String(),
    ];

    foreach ($keys as $key => $value) {
        SiteSetting::create([
            'key'   => $key,
            'value' => Crypt::encryptString($value),
            'group' => 'finance',
            'type'  => 'encrypted',
        ]);
        Cache::forget("site_setting:{$key}");
    }

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

function seedSyncIncomeAccount(): void
{
    SiteSetting::create([
        'key'   => 'qb_income_account_id',
        'value' => '42',
        'group' => 'finance',
        'type'  => 'string',
    ]);
    Cache::forget('site_setting:qb_income_account_id');
}

// ── Skip conditions ────────────────────────────────────────────────────────

it('skips sync when QuickBooks is not connected', function () {
    $transaction = Transaction::factory()->create(['status' => 'completed']);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldNotReceive('createSalesReceipt');
    $mock->shouldNotReceive('createRefundReceipt');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    expect($transaction->fresh()->quickbooks_id)->toBeNull();
});

it('skips sync when no income account is configured', function () {
    seedQbSyncConnection();

    $transaction = Transaction::factory()->create(['status' => 'completed']);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldNotReceive('createSalesReceipt');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    expect($transaction->fresh()->quickbooks_id)->toBeNull();
});

it('skips sync when transaction already has quickbooks_id', function () {
    seedQbSyncConnection();
    seedSyncIncomeAccount();

    $transaction = Transaction::factory()->create([
        'status'        => 'completed',
        'quickbooks_id' => 'existing-qb-id',
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldNotReceive('createSalesReceipt');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();
});

// ── Success paths ──────────────────────────────────────────────────────────

it('creates a sales receipt for inbound transactions', function () {
    seedQbSyncConnection();
    seedSyncIncomeAccount();

    $transaction = Transaction::factory()->create([
        'direction' => 'in',
        'status'    => 'completed',
        'amount'    => 50.00,
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')->andReturn(null);
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->with(Mockery::on(fn ($t) => $t->id === $transaction->id), null)
        ->andReturn('QB-SR-123');
    $mock->shouldNotReceive('createRefundReceipt');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-SR-123');
    expect($transaction->qb_synced_at)->not->toBeNull();
    expect($transaction->qb_sync_error)->toBeNull();
});

it('creates a refund receipt for outbound transactions', function () {
    seedQbSyncConnection();
    seedSyncIncomeAccount();

    $transaction = Transaction::factory()->create([
        'direction' => 'out',
        'type'      => 'refund',
        'status'    => 'completed',
        'amount'    => 25.00,
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')->andReturn(null);
    $mock->shouldReceive('createRefundReceipt')
        ->once()
        ->with(Mockery::on(fn ($t) => $t->id === $transaction->id), null)
        ->andReturn('QB-RR-456');
    $mock->shouldNotReceive('createSalesReceipt');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-RR-456');
    expect($transaction->qb_synced_at)->not->toBeNull();
    expect($transaction->qb_sync_error)->toBeNull();
});

// ── Failure path ───────────────────────────────────────────────────────────

it('stores sync error on failure without setting quickbooks_id', function () {
    seedQbSyncConnection();
    seedSyncIncomeAccount();

    $transaction = Transaction::factory()->create([
        'direction' => 'in',
        'status'    => 'completed',
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')->andReturn(null);
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->andThrow(new RuntimeException('QuickBooks API unavailable'));
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBeNull();
    expect($transaction->qb_synced_at)->toBeNull();
    expect($transaction->qb_sync_error)->toContain('QuickBooks API unavailable');
});

it('redacts bearer tokens from sync error messages', function () {
    seedQbSyncConnection();
    seedSyncIncomeAccount();

    $transaction = Transaction::factory()->create([
        'direction' => 'in',
        'status'    => 'completed',
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')->andReturn(null);
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->andThrow(new RuntimeException('401 Unauthorized Bearer eyJhbGciOiJSUz...secret_token'));
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->qb_sync_error)->not->toContain('eyJhbGciOiJSUz');
    expect($transaction->qb_sync_error)->toContain('[REDACTED]');
});

it('clears previous sync error on successful retry', function () {
    seedQbSyncConnection();
    seedSyncIncomeAccount();

    $transaction = Transaction::factory()->create([
        'direction'     => 'in',
        'status'        => 'completed',
        'qb_sync_error' => 'Previous error message',
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')->andReturn(null);
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->andReturn('QB-SR-789');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-SR-789');
    expect($transaction->qb_sync_error)->toBeNull();
});

// ── Income account setting ─────────────────────────────────────────────────

it('stores and retrieves the income account setting', function () {
    seedSyncIncomeAccount();

    expect(SiteSetting::get('qb_income_account_id'))->toBe('42');
});

// ── Permission gate ────────────────────────────────────────────────────────

it('denies manage_financial_settings to users without the permission', function () {
    $user = User::factory()->create();

    expect($user->can('manage_financial_settings'))->toBeFalse();
});
