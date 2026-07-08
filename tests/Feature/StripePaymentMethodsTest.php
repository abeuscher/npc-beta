<?php

use App\Models\SiteSetting;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// The filtering tests invoke the REAL StripeCheckoutService::buildParams()
// (the method every checkout path calls) — not a re-implemented copy of the
// array_intersect. The previous versions rebuilt the subscription filter and
// card fallback inline in the test bodies, so the production logic in
// StripeCheckoutService could regress without a single failure here.

function buildCheckoutParams(string $mode): array
{
    return app(StripeCheckoutService::class)->buildParams(
        lineItems: [['price' => 'price_test', 'quantity' => 1]],
        metadata: [],
        successUrl: 'https://example.test/success',
        cancelUrl: 'https://example.test/cancel',
        mode: $mode,
    );
}

function setPaymentMethodTypes(array $types): void
{
    SiteSetting::create([
        'key'   => 'stripe_payment_method_types',
        'value' => json_encode($types),
        'group' => 'finance',
        'type'  => 'json',
    ]);
    Cache::forget('site_setting:stripe_payment_method_types');
}

// ── Payment method setting storage and retrieval ────────────────────────────

it('stores payment method types as JSON site setting', function () {
    setPaymentMethodTypes(['card', 'us_bank_account', 'link']);

    $stored = SiteSetting::get('stripe_payment_method_types');
    expect($stored)->toBe(['card', 'us_bank_account', 'link']);
});

it('returns null for missing payment method types setting', function () {
    $stored = SiteSetting::get('stripe_payment_method_types');
    expect($stored)->toBeNull();
});

// ── buildParams payment_method_types derivation ─────────────────────────────

it('buildParams passes the configured payment methods through verbatim in payment mode', function () {
    setPaymentMethodTypes(['card', 'cashapp']);

    $params = buildCheckoutParams('payment');

    expect($params['payment_method_types'])->toBe(['card', 'cashapp'])
        ->and($params['mode'])->toBe('payment');
});

it('buildParams filters the configured methods to subscription-compatible types in subscription mode', function () {
    setPaymentMethodTypes(['card', 'us_bank_account', 'cashapp', 'amazon_pay']);

    $params = buildCheckoutParams('subscription');

    expect($params['payment_method_types'])->toBe(['card', 'us_bank_account'])
        ->and($params['payment_method_types'])->not->toContain('cashapp')
        ->and($params['payment_method_types'])->not->toContain('amazon_pay');
});

it('buildParams falls back to card in subscription mode when no configured method is subscription-compatible', function () {
    setPaymentMethodTypes(['cashapp', 'amazon_pay']);

    $params = buildCheckoutParams('subscription');

    expect($params['payment_method_types'])->toBe(['card']);
});

it('buildParams defaults the payment methods to card when the setting does not exist', function () {
    Cache::forget('site_setting:stripe_payment_method_types');

    $params = buildCheckoutParams('payment');

    expect($params['payment_method_types'])->toBe(['card']);
});

it('buildParams defaults the payment methods to card when the setting is an empty list', function () {
    setPaymentMethodTypes([]);

    $params = buildCheckoutParams('payment');

    expect($params['payment_method_types'])->toBe(['card']);
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
