<?php

use App\Models\Donation;
use App\Models\Fund;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

    // We only need the resolveIncomeAccount logic — no QB API calls.
    // Create a partial mock so resolveIncomeAccount runs real code.
    $this->client = new QuickBooksClient();
});

function seedGlobalDefault(string $id = '42'): void
{
    SiteSetting::create([
        'key'   => 'qb_income_account_id',
        'value' => $id,
        'group' => 'finance',
        'type'  => 'string',
    ]);
    Cache::forget('site_setting:qb_income_account_id');
}

function seedAccountMap(array $map): void
{
    SiteSetting::create([
        'key'   => 'qb_account_map',
        'value' => json_encode($map),
        'group' => 'finance',
        'type'  => 'json',
    ]);
    Cache::forget('site_setting:qb_account_map');
}

// ── Resolution order ──────────────────────────────────────────────────────

it('returns fund-level override when donation has a fund with QB account', function () {
    $fund = Fund::factory()->create(['quickbooks_account_id' => '99']);
    $donation = Donation::factory()->create(['fund_id' => $fund->id]);
    $transaction = Transaction::factory()->create([
        'subject_type' => Donation::class,
        'subject_id'   => $donation->id,
    ]);

    seedAccountMap(['default' => '42', 'donation' => '55']);

    expect($this->client->resolveIncomeAccount($transaction))->toBe('99');
});

it('returns type-level mapping for donation when no fund override', function () {
    $donation = Donation::factory()->create();
    $transaction = Transaction::factory()->create([
        'subject_type' => Donation::class,
        'subject_id'   => $donation->id,
    ]);

    seedAccountMap(['default' => '42', 'donation' => '55']);

    expect($this->client->resolveIncomeAccount($transaction))->toBe('55');
});

it('returns type-level mapping for purchase', function () {
    $product = Product::factory()->create();
    $price = ProductPrice::factory()->create(['product_id' => $product->id]);
    $purchase = Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
    ]);
    $transaction = Transaction::factory()->create([
        'subject_type' => Purchase::class,
        'subject_id'   => $purchase->id,
    ]);

    seedAccountMap(['default' => '42', 'purchase' => '66']);

    expect($this->client->resolveIncomeAccount($transaction))->toBe('66');
});

it('falls back to account map default when type has no mapping', function () {
    $transaction = Transaction::factory()->create([
        'subject_type' => null,
        'subject_id'   => null,
    ]);

    seedAccountMap(['default' => '42']);

    expect($this->client->resolveIncomeAccount($transaction))->toBe('42');
});

it('falls back to qb_income_account_id when account map has no default', function () {
    $transaction = Transaction::factory()->create([
        'subject_type' => null,
        'subject_id'   => null,
    ]);

    seedGlobalDefault('88');

    expect($this->client->resolveIncomeAccount($transaction))->toBe('88');
});

it('throws when nothing is configured', function () {
    $transaction = Transaction::factory()->create([
        'subject_type' => null,
        'subject_id'   => null,
    ]);

    $this->client->resolveIncomeAccount($transaction);
})->throws(RuntimeException::class, 'No QuickBooks deposit account configured');

it('ignores fund override when fund has no QB account set', function () {
    $fund = Fund::factory()->create(['quickbooks_account_id' => null]);
    $donation = Donation::factory()->create(['fund_id' => $fund->id]);
    $transaction = Transaction::factory()->create([
        'subject_type' => Donation::class,
        'subject_id'   => $donation->id,
    ]);

    seedAccountMap(['default' => '42', 'donation' => '55']);

    expect($this->client->resolveIncomeAccount($transaction))->toBe('55');
});

// ── Account map setting persistence ───────────────────────────────────────

it('stores and retrieves qb_account_map JSON', function () {
    $map = ['default' => '42', 'donation' => '55', 'purchase' => '66'];
    seedAccountMap($map);

    $stored = SiteSetting::get('qb_account_map');

    expect($stored)->toBe($map);
});

// ── Fund QB account field ─────────────────────────────────────────────────

it('saves and retrieves quickbooks_account_id on fund', function () {
    $fund = Fund::factory()->create(['quickbooks_account_id' => '77']);

    expect($fund->fresh()->quickbooks_account_id)->toBe('77');
});

it('allows null quickbooks_account_id on fund', function () {
    $fund = Fund::factory()->create();

    expect($fund->fresh()->quickbooks_account_id)->toBeNull();
});
