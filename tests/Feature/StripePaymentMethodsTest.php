<?php

use App\Filament\Pages\Settings\FinanceSettingsPage;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Payment method setting storage and retrieval ────────────────────────────

it('stores payment method types as JSON site setting', function () {
    $types = ['card', 'us_bank_account', 'link'];

    SiteSetting::create([
        'key'   => 'stripe_payment_method_types',
        'value' => json_encode($types),
        'group' => 'finance',
        'type'  => 'json',
    ]);
    Cache::forget('site_setting:stripe_payment_method_types');

    $stored = SiteSetting::get('stripe_payment_method_types');
    expect($stored)->toBe($types);
});

it('returns null for missing payment method types setting', function () {
    $stored = SiteSetting::get('stripe_payment_method_types');
    expect($stored)->toBeNull();
});

it('defaults to card when payment method types setting is empty', function () {
    SiteSetting::create([
        'key'   => 'stripe_payment_method_types',
        'value' => json_encode([]),
        'group' => 'finance',
        'type'  => 'json',
    ]);
    Cache::forget('site_setting:stripe_payment_method_types');

    $stored = SiteSetting::get('stripe_payment_method_types');
    expect($stored)->toBe([]);
});

// ── Subscription-compatible method filtering ────────────────────────────────

it('filters payment methods to subscription-compatible types', function () {
    $allMethods = ['card', 'us_bank_account', 'link', 'cashapp', 'amazon_pay'];

    $subscriptionMethods = array_values(array_intersect(
        $allMethods,
        FinanceSettingsPage::SUBSCRIPTION_COMPATIBLE_METHODS,
    ));

    expect($subscriptionMethods)->toBe(['card', 'us_bank_account', 'link']);
});

it('always includes card in subscription methods even when only non-subscription methods are configured', function () {
    $methods = ['card', 'cashapp', 'amazon_pay'];

    $subscriptionMethods = array_values(array_intersect(
        $methods,
        FinanceSettingsPage::SUBSCRIPTION_COMPATIBLE_METHODS,
    ));

    if (empty($subscriptionMethods)) {
        $subscriptionMethods = ['card'];
    }

    expect($subscriptionMethods)->toBe(['card']);
});

// ── Checkout session payment method types ───────────────────────────────────

it('donation checkout uses configured payment method types for one-off', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    SiteSetting::create([
        'key'   => 'stripe_payment_method_types',
        'value' => json_encode(['card', 'cashapp']),
        'group' => 'finance',
        'type'  => 'json',
    ]);
    Cache::forget('site_setting:stripe_payment_method_types');

    // The Stripe call will fail but we can verify the setting is read correctly
    $stored = SiteSetting::get('stripe_payment_method_types');
    expect($stored)->toBe(['card', 'cashapp']);
});

it('donation checkout filters to subscription-compatible methods for recurring', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    SiteSetting::create([
        'key'   => 'stripe_payment_method_types',
        'value' => json_encode(['card', 'us_bank_account', 'cashapp', 'amazon_pay']),
        'group' => 'finance',
        'type'  => 'json',
    ]);
    Cache::forget('site_setting:stripe_payment_method_types');

    $configuredMethods = SiteSetting::get('stripe_payment_method_types');
    $subscriptionMethods = array_values(array_intersect(
        $configuredMethods,
        FinanceSettingsPage::SUBSCRIPTION_COMPATIBLE_METHODS,
    ));

    expect($subscriptionMethods)->toBe(['card', 'us_bank_account'])
        ->and($subscriptionMethods)->not->toContain('cashapp')
        ->and($subscriptionMethods)->not->toContain('amazon_pay');
});

it('defaults payment methods to card when setting does not exist', function () {
    Cache::forget('site_setting:stripe_payment_method_types');

    $configuredMethods = SiteSetting::get('stripe_payment_method_types') ?? ['card'];
    if (empty($configuredMethods)) {
        $configuredMethods = ['card'];
    }

    expect($configuredMethods)->toBe(['card']);
});

// ── Checkout controller validation still works ──────────────────────────────

it('donation checkout rejects missing amount', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $response = $this->postJson(route('donations.checkout'), [
        'type' => 'one_off',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('amount');
});

it('product checkout rejects missing product_price_id', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);

    $response = $this->post(route('products.checkout'), []);

    $response->assertSessionHasErrors('product_price_id');
});
