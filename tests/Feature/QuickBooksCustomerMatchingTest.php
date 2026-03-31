<?php

use App\Jobs\SyncTransactionToQuickBooks;
use App\Models\Contact;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Services\QuickBooks\QuickBooksAuth;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
});

function seedQbConnection(): void
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

    SiteSetting::create([
        'key'   => 'qb_income_account_id',
        'value' => '42',
        'group' => 'finance',
        'type'  => 'string',
    ]);
    Cache::forget('site_setting:qb_income_account_id');
}

// ── findOrCreateCustomer unit-level tests ─────────────────────────────────

it('returns null for contacts without an email', function () {
    $contact = Contact::factory()->create(['email' => null]);

    $client = Mockery::mock(QuickBooksClient::class)->makePartial();

    expect($client->findOrCreateCustomer($contact))->toBeNull();
    expect($contact->fresh()->quickbooks_customer_id)->toBeNull();
});

it('returns cached quickbooks_customer_id without querying QB', function () {
    $contact = Contact::factory()->create([
        'email' => 'test@example.com',
        'quickbooks_customer_id' => 'QB-CUST-99',
    ]);

    // findOrCreateCustomer should return the cached ID directly — no QB API call needed
    $client = new QuickBooksClient;

    expect($client->findOrCreateCustomer($contact))->toBe('QB-CUST-99');
});

// ── Sync job — customer matching integration ──────────────────────────────

it('attaches CustomerRef when contact has a QB customer', function () {
    seedQbConnection();

    $contact = Contact::factory()->create([
        'email' => 'donor@example.com',
        'quickbooks_customer_id' => 'QB-CUST-42',
    ]);

    $transaction = Transaction::factory()->create([
        'direction'  => 'in',
        'status'     => 'completed',
        'contact_id' => $contact->id,
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')
        ->once()
        ->with(Mockery::on(fn ($c) => $c->id === $contact->id))
        ->andReturn('QB-CUST-42');
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->with(Mockery::on(fn ($t) => $t->id === $transaction->id), 'QB-CUST-42')
        ->andReturn('QB-SR-100');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-SR-100');
});

it('omits CustomerRef when transaction has no contact', function () {
    seedQbConnection();

    $transaction = Transaction::factory()->create([
        'direction'  => 'in',
        'status'     => 'completed',
        'contact_id' => null,
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldNotReceive('findOrCreateCustomer');
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->with(Mockery::on(fn ($t) => $t->id === $transaction->id), null)
        ->andReturn('QB-SR-200');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-SR-200');
});

it('omits CustomerRef when contact has no email', function () {
    seedQbConnection();

    $contact = Contact::factory()->create(['email' => null]);

    $transaction = Transaction::factory()->create([
        'direction'  => 'in',
        'status'     => 'completed',
        'contact_id' => $contact->id,
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')
        ->once()
        ->with(Mockery::on(fn ($c) => $c->id === $contact->id))
        ->andReturn(null);
    $mock->shouldReceive('createSalesReceipt')
        ->once()
        ->with(Mockery::on(fn ($t) => $t->id === $transaction->id), null)
        ->andReturn('QB-SR-300');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-SR-300');
});

it('attaches CustomerRef on refund receipt for outbound transactions', function () {
    seedQbConnection();

    $contact = Contact::factory()->create([
        'email' => 'refund@example.com',
        'quickbooks_customer_id' => 'QB-CUST-77',
    ]);

    $transaction = Transaction::factory()->create([
        'direction'  => 'out',
        'type'       => 'refund',
        'status'     => 'completed',
        'contact_id' => $contact->id,
    ]);

    $mock = Mockery::mock(QuickBooksClient::class);
    $mock->shouldReceive('findOrCreateCustomer')
        ->once()
        ->andReturn('QB-CUST-77');
    $mock->shouldReceive('createRefundReceipt')
        ->once()
        ->with(Mockery::on(fn ($t) => $t->id === $transaction->id), 'QB-CUST-77')
        ->andReturn('QB-RR-100');
    app()->instance(QuickBooksClient::class, $mock);

    (new SyncTransactionToQuickBooks($transaction))->handle();

    $transaction->refresh();
    expect($transaction->quickbooks_id)->toBe('QB-RR-100');
});

// ── Contact edit page — QB link status ────────────────────────────────────

it('shows QB linked status on contact edit page when connected', function () {
    seedQbConnection();

    $contact = Contact::factory()->create([
        'email' => 'linked@example.com',
        'quickbooks_customer_id' => 'QB-CUST-55',
    ]);

    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get("/admin/contacts/{$contact->id}/edit")
        ->assertOk()
        ->assertSee('Linked')
        ->assertSee('QB Customer #QB-CUST-55');
});

it('shows not linked status on contact edit page when no QB customer', function () {
    seedQbConnection();

    $contact = Contact::factory()->create([
        'email' => 'unlinked@example.com',
        'quickbooks_customer_id' => null,
    ]);

    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get("/admin/contacts/{$contact->id}/edit")
        ->assertOk()
        ->assertSee('Not linked');
});
