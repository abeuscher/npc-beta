<?php

use App\Models\Event;
use App\Models\MembershipTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SiteSetting;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Checkout\Session;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Fake — captures the params buildParams() would produce, no Stripe HTTP ───

class FakeStripeCheckoutService extends StripeCheckoutService
{
    public array $lastParams = [];
    public ?string $lastSubmitType = null;

    public function createSession(
        array $lineItems,
        array $metadata,
        string $successUrl,
        string $cancelUrl,
        string $mode = 'payment',
        ?string $submitType = null,
        array $extra = [],
    ): Session {
        $this->lastSubmitType = $submitType;
        $this->lastParams = $this->buildParams(
            lineItems: $lineItems,
            metadata: $metadata,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            mode: $mode,
            submitType: $submitType,
            extra: $extra,
        );

        return Session::constructFrom([
            'id'  => 'cs_test_fake',
            'url' => 'https://checkout.stripe.com/test_fake',
        ]);
    }
}

function bindFakeStripe(): FakeStripeCheckoutService
{
    $fake = new FakeStripeCheckoutService();
    app()->instance(StripeCheckoutService::class, $fake);

    return $fake;
}

// ── buildParams() — branding-field shaping logic ─────────────────────────────

it('omits custom_text and statement descriptors when no SiteSettings are configured', function () {
    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [['price_data' => ['currency' => 'usd', 'unit_amount' => 1000, 'product_data' => ['name' => 'X']], 'quantity' => 1]],
        metadata: ['donation_id' => 'abc'],
        successUrl: 'https://example.com/success',
        cancelUrl: 'https://example.com/cancel',
    );

    expect($params)->not->toHaveKey('custom_text')
        ->and($params)->not->toHaveKey('payment_intent_data')
        ->and($params)->not->toHaveKey('consent_collection')
        ->and($params)->not->toHaveKey('submit_type');
});

it('emits custom_text.submit when stripe_checkout_submit_text is set', function () {
    SiteSetting::set('stripe_checkout_submit_text', 'Thank you for your support.');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x',
    );

    expect($params['custom_text']['submit']['message'])->toBe('Thank you for your support.');
});

it('emits custom_text.after_submit when stripe_checkout_after_submit_text is set', function () {
    SiteSetting::set('stripe_checkout_after_submit_text', 'Look for an email shortly.');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x',
    );

    expect($params['custom_text']['after_submit']['message'])->toBe('Look for an email shortly.');
});

it('does not emit custom_text.terms_of_service_acceptance when ToS toggle is off', function () {
    SiteSetting::set('stripe_checkout_terms_acceptance_text', 'I agree to the terms.');
    SiteSetting::set('stripe_tos_url_configured', 'false');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x',
    );

    expect($params)->not->toHaveKey('custom_text')
        ->and($params)->not->toHaveKey('consent_collection');
});

it('emits ToS consent_collection and custom_text.terms_of_service_acceptance when ToS toggle is on', function () {
    SiteSetting::set('stripe_checkout_terms_acceptance_text', 'I agree to the terms.');
    SiteSetting::set('stripe_tos_url_configured', 'true');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x',
    );

    expect($params['custom_text']['terms_of_service_acceptance']['message'])->toBe('I agree to the terms.')
        ->and($params['consent_collection']['terms_of_service'])->toBe('required');
});

it('emits payment_intent_data.statement_descriptor in payment mode when set', function () {
    SiteSetting::set('stripe_statement_descriptor', 'ACME FOUNDATION');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x', mode: 'payment',
    );

    expect($params['payment_intent_data']['statement_descriptor'])->toBe('ACME FOUNDATION');
});

it('emits statement_descriptor_suffix in payment mode when set', function () {
    SiteSetting::set('stripe_statement_descriptor_suffix', 'EVENT');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x', mode: 'payment',
    );

    expect($params['payment_intent_data']['statement_descriptor_suffix'])->toBe('EVENT');
});

it('omits statement_descriptor in subscription mode even when set', function () {
    SiteSetting::set('stripe_statement_descriptor', 'ACME FOUNDATION');
    SiteSetting::set('stripe_statement_descriptor_suffix', 'EVENT');

    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x', mode: 'subscription',
    );

    expect($params)->not->toHaveKey('payment_intent_data');
});

it('emits submit_type in payment mode when provided', function () {
    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x',
        mode: 'payment', submitType: 'donate',
    );

    expect($params['submit_type'])->toBe('donate');
});

it('omits submit_type in subscription mode regardless of input', function () {
    $params = (new StripeCheckoutService())->buildParams(
        lineItems: [], metadata: [], successUrl: 'https://x', cancelUrl: 'https://x',
        mode: 'subscription', submitType: 'pay',
    );

    expect($params)->not->toHaveKey('submit_type');
});

it('returns null defaultImageUrl when no SiteSetting exists for that flow', function () {
    expect(StripeCheckoutService::defaultImageUrl('donation'))->toBeNull()
        ->and(StripeCheckoutService::defaultImageUrl('event'))->toBeNull()
        ->and(StripeCheckoutService::defaultImageUrl('product'))->toBeNull()
        ->and(StripeCheckoutService::defaultImageUrl('membership'))->toBeNull();
});

it('returns absolute URL from defaultImageUrl when SiteSetting points to a stored path', function () {
    SiteSetting::set('stripe_default_donation_image', 'site/stripe-branding/donation.png');

    $url = StripeCheckoutService::defaultImageUrl('donation');

    expect($url)->toContain('site/stripe-branding/donation.png');
});

// ── Per-call-site verification — spy on the resolved StripeCheckoutService ───

it('DonationCheckoutController passes submitType=donate for one-off donations', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $fake = bindFakeStripe();

    $this->postJson(route('donations.checkout'), [
        'amount' => 50.00,
        'type'   => 'one_off',
    ])->assertOk();

    expect($fake->lastSubmitType)->toBe('donate')
        ->and($fake->lastParams['submit_type'])->toBe('donate')
        ->and($fake->lastParams['mode'])->toBe('payment');
});

it('DonationCheckoutController omits submitType for subscription-mode recurring donations', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $fake = bindFakeStripe();

    $this->postJson(route('donations.checkout'), [
        'amount'    => 25.00,
        'type'      => 'recurring',
        'frequency' => 'monthly',
    ])->assertOk();

    expect($fake->lastParams['mode'])->toBe('subscription')
        ->and($fake->lastParams)->not->toHaveKey('submit_type');
});

it('DonationCheckoutController attaches default donation image to line items when configured', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    SiteSetting::set('stripe_default_donation_image', 'site/stripe-branding/donation.png');
    $fake = bindFakeStripe();

    $this->postJson(route('donations.checkout'), [
        'amount' => 50.00,
        'type'   => 'one_off',
    ])->assertOk();

    $images = $fake->lastParams['line_items'][0]['price_data']['product_data']['images'] ?? null;

    expect($images)->toBeArray()
        ->and($images[0])->toContain('site/stripe-branding/donation.png');
});

it('ProductCheckoutController passes submitType=pay', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $fake = bindFakeStripe();

    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create([
        'product_id'      => $product->id,
        'stripe_price_id' => null,
    ]);

    $this->post(route('products.checkout'), [
        'product_price_id' => $price->id,
    ])->assertRedirect();

    expect($fake->lastSubmitType)->toBe('pay')
        ->and($fake->lastParams['submit_type'])->toBe('pay');
});

it('MembershipCheckoutController one-off path passes submitType=pay and uses default membership image', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    SiteSetting::set('stripe_default_membership_image', 'site/stripe-branding/membership.png');
    $fake = bindFakeStripe();

    $tier = MembershipTier::factory()->create([
        'default_price'    => 50.00,
        'is_active'        => true,
        'billing_interval' => 'one_time',
    ]);

    $this->post(route('membership.checkout'), [
        'tier_id'               => $tier->id,
        'first_name'            => 'Test',
        'last_name'             => 'User',
        'email'                 => 'fresh-' . uniqid() . '@example.com',
        'password'              => 'password-of-some-length',
        'password_confirmation' => 'password-of-some-length',
    ])->assertRedirect();

    $images = $fake->lastParams['line_items'][0]['price_data']['product_data']['images'] ?? null;

    expect($fake->lastSubmitType)->toBe('pay')
        ->and($fake->lastParams['submit_type'])->toBe('pay')
        ->and($images[0] ?? null)->toContain('site/stripe-branding/membership.png');
});

it('MembershipCheckoutController subscription path omits submit_type', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    $fake = bindFakeStripe();

    $tier = MembershipTier::factory()->create([
        'default_price'    => 50.00,
        'is_active'        => true,
        'billing_interval' => 'monthly',
    ]);

    $this->post(route('membership.checkout'), [
        'tier_id'               => $tier->id,
        'first_name'            => 'Test',
        'last_name'             => 'User',
        'email'                 => 'fresh-' . uniqid() . '@example.com',
        'password'              => 'password-of-some-length',
        'password_confirmation' => 'password-of-some-length',
    ])->assertRedirect();

    expect($fake->lastParams['mode'])->toBe('subscription')
        ->and($fake->lastParams)->not->toHaveKey('submit_type');
});

it('Public EventController paid path passes submitType=pay and propagates event image to line items', function () {
    config(['services.stripe.secret' => 'sk_test_fake']);
    SiteSetting::set('stripe_default_event_image', 'site/stripe-branding/event.png');
    $fake = bindFakeStripe();

    $event = Event::factory()->paid(25.00)->create();
    $tier  = $event->ticketTiers()->first();

    $this->post(route('events.register', $event->slug), [
        'name'        => 'Test User',
        'email'       => 'test@example.com',
        'quantities'  => [$tier->id => 1],
        '_form_start' => time() - 10,
    ])->assertRedirect();

    $images = $fake->lastParams['line_items'][0]['price_data']['product_data']['images'] ?? null;

    expect($fake->lastSubmitType)->toBe('pay')
        ->and($fake->lastParams['submit_type'])->toBe('pay')
        ->and($images[0] ?? null)->toContain('site/stripe-branding/event.png');
});
