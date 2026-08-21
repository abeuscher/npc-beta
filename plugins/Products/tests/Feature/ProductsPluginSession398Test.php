<?php

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Payments\Events\CheckoutSettled;
use App\Plugins\CapabilityRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 398 (Plugin Architecture arc D8): the enabled twin for the carved
// Products vertical. The full composition must be behavior-identical to the
// pre-carve state: same routes, same widgets, same admin surface, same
// observer effects, same purchase fulfillment (now via the CheckoutSettled
// listener — the third cross-repo inversion, Payments v0.4.0). The plugin
// declares NO capability — nothing consumes products presence; the presence
// signal is route presence (Route::has('products.checkout')).

it('loads the Products provider as the ninth plugin', function () {
    expect(config('plugins'))->toContain(\Plugins\Products\ProductsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Products\ProductsServiceProvider::class))->toBeTrue();
});

it('declares no products capability — nothing consumes products presence', function () {
    expect(app(CapabilityRegistry::class)->present('products'))->toBeFalse();
});

it('registers both product routes with their pre-carve URIs and middleware', function () {
    expect(Route::has('products.checkout'))->toBeTrue()
        ->and(Route::has('products.waitlist'))->toBeTrue();

    $checkout = Route::getRoutes()->getByName('products.checkout');
    expect($checkout->uri())->toBe('products/checkout')
        ->and($checkout->methods())->toContain('POST')
        ->and($checkout->gatherMiddleware())->toContain('throttle:20,1')
        ->and($checkout->gatherMiddleware())->toContain('web')
        ->and($checkout->getActionName())
            ->toBe(\Plugins\Products\Http\Controllers\ProductCheckoutController::class . '@store');

    $waitlist = Route::getRoutes()->getByName('products.waitlist');
    expect($waitlist->uri())->toBe('products/waitlist')
        ->and($waitlist->methods())->toContain('POST')
        ->and($waitlist->gatherMiddleware())->toContain('throttle:10,1')
        ->and($waitlist->gatherMiddleware())->toContain('web')
        ->and($waitlist->getActionName())
            ->toBe(\Plugins\Products\Http\Controllers\ProductWaitlistController::class . '@store');
});

it('registers the three ProductResource admin routes through the admin socket', function () {
    foreach ([
        'filament.admin.resources.products.index',
        'filament.admin.resources.products.create',
        'filament.admin.resources.products.edit',
    ] as $name) {
        expect(Route::has($name))->toBeTrue("route {$name} should exist");
    }
});

it('registers both product widgets from the plugin provider', function () {
    $handles = collect(app(WidgetRegistry::class)->all())->map(fn ($d) => $d->handle());

    expect($handles)->toContain('product_display')
        ->and($handles)->toContain('product_carousel');
});

it('renders the widget templates through the plugin view namespace', function () {
    expect(view()->exists('plugin-products-widgets::ProductDisplay.template'))->toBeTrue()
        ->and(view()->exists('plugin-products-widgets::ProductCarousel.template'))->toBeTrue();
});

it('registers the product policy via Gate::policy', function () {
    expect(Gate::getPolicyFor(Product::class))
        ->toBeInstanceOf(\Plugins\Products\Policies\ProductPolicy::class);
});

// ── The double observer inversion (front-loaded decision 5) ──────────────────

it('stamps published_at on publish-create — the ProductObserver is attached', function () {
    $product = Product::create([
        'name'     => 'Observer Stamped',
        'slug'     => 'observer-stamped',
        'status'   => 'published',
        'capacity' => 10,
    ]);

    expect($product->published_at)->not->toBeNull();
});

it('logs purchase activity on create — the PurchaseObserver is attached', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);
    $contact = Contact::factory()->create();

    $purchase = Purchase::create([
        'product_id'        => $product->id,
        'product_price_id'  => $price->id,
        'contact_id'        => $contact->id,
        'stripe_session_id' => 'cs_test_observer_398',
        'amount_paid'       => 25.00,
        'status'            => 'active',
        'occurred_at'       => now(),
    ]);

    expect(ActivityLog::where('subject_type', Contact::class)
        ->where('subject_id', (string) $contact->id)
        ->where('event', 'purchased')
        ->exists())->toBeTrue();
});

// ── The CheckoutSettled listener (the third cross-repo inversion) ────────────

it('wires the CheckoutSettled listener: dispatch records the purchase, resolves the contact, records one transaction', function () {
    $product = Product::factory()->create(['capacity' => 10]);
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);

    event(new CheckoutSettled((object) [
        'id'               => 'cs_test_prod_398',
        'payment_intent'   => 'pi_prod_398',
        'amount_total'     => 3500,
        'customer_details' => (object) ['email' => 'buyer398@example.com', 'name' => 'Pat Buyer'],
    ], (object) ['product_price_id' => $price->id]));

    $purchase = Purchase::where('stripe_session_id', 'cs_test_prod_398')->first();
    $contact  = Contact::where('email', 'buyer398@example.com')->first();

    expect($purchase)->not->toBeNull()
        ->and($purchase->product_id)->toBe($product->id)
        ->and($purchase->amount_paid + 0)->toEqual(35.0)
        ->and($purchase->status)->toBe('active')
        ->and($contact)->not->toBeNull()
        ->and($contact->first_name)->toBe('Pat')
        ->and($purchase->contact_id)->toBe($contact->id)
        ->and(Transaction::where('stripe_id', 'pi_prod_398')->count())->toBe(1);
});

it('reuses an existing contact by email instead of creating a duplicate', function () {
    $product = Product::factory()->create(['capacity' => 10]);
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);
    $contact = Contact::factory()->create(['email' => 'repeat398@example.com']);

    event(new CheckoutSettled((object) [
        'id'               => 'cs_test_repeat_398',
        'payment_intent'   => 'pi_repeat_398',
        'amount_total'     => 1000,
        'customer_details' => (object) ['email' => 'repeat398@example.com', 'name' => 'Repeat Buyer'],
    ], (object) ['product_price_id' => $price->id]));

    expect(Contact::where('email', 'repeat398@example.com')->count())->toBe(1)
        ->and(Purchase::where('stripe_session_id', 'cs_test_repeat_398')->first()->contact_id)
            ->toBe($contact->id);
});

it('replays idempotently: a second CheckoutSettled dispatch writes nothing new (the session-id guard)', function () {
    $product = Product::factory()->create(['capacity' => 10]);
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);

    $payload = (object) [
        'id'               => 'cs_test_replay_398',
        'payment_intent'   => 'pi_replay_398',
        'amount_total'     => 2000,
        'customer_details' => (object) ['email' => 'replay398@example.com', 'name' => 'Replay Buyer'],
    ];
    $metadata = (object) ['product_price_id' => $price->id];

    event(new CheckoutSettled($payload, $metadata));
    event(new CheckoutSettled($payload, $metadata));

    expect(Purchase::where('stripe_session_id', 'cs_test_replay_398')->count())->toBe(1)
        ->and(Transaction::where('stripe_id', 'pi_replay_398')->count())->toBe(1);
});

it('no-ops on CheckoutSettled dispatches routed to another vertical', function () {
    event(new CheckoutSettled((object) [
        'id'               => 'cs_test_other_vertical_398',
        'payment_intent'   => 'pi_other_398',
        'amount_total'     => 5000,
        'customer_details' => (object) ['email' => 'other398@example.com', 'name' => 'Other Vertical'],
    ], (object) ['donation_id' => (string) \Illuminate\Support\Str::uuid()]));

    expect(Purchase::count())->toBe(0)
        ->and(Contact::where('email', 'other398@example.com')->exists())->toBeFalse();
});

// ── The webhook dispatch (the Payments v0.4.0 side of the seam) ──────────────

it('dispatches CheckoutSettled from the webhook product branch', function () {
    \Illuminate\Support\Facades\Event::fake([CheckoutSettled::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleCheckoutSessionCompleted');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'             => 'cs_test_prod_dispatch',
        'payment_intent' => 'pi_prod_dispatch',
        'amount_total'   => 3500,
        'metadata'       => ['product_price_id' => 'some-price-id'],
    ]]]);

    $response = $reflection->invoke($controller, $stripeEvent);

    expect($response->getStatusCode())->toBe(200);
    \Illuminate\Support\Facades\Event::assertDispatched(CheckoutSettled::class);
});

it('never reaches the generic fallback for a product-tagged session — no orphan transaction', function () {
    // The product branch sits ahead of the generic fallback in the webhook.
    // With Event::fake the listener never runs, so a fallthrough would be
    // visible as a generic Transaction row for the session's payment intent.
    \Illuminate\Support\Facades\Event::fake([CheckoutSettled::class]);

    $controller = new \Plugins\Payments\Http\Controllers\StripeWebhookController();
    $reflection = new ReflectionMethod($controller, 'handleCheckoutSessionCompleted');
    $reflection->setAccessible(true);

    $stripeEvent = \Stripe\Event::constructFrom(['data' => ['object' => [
        'id'             => 'cs_test_prod_ordering',
        'payment_intent' => 'pi_prod_ordering',
        'amount_total'   => 3500,
        'metadata'       => ['product_price_id' => 'some-price-id'],
    ]]]);

    $reflection->invoke($controller, $stripeEvent);

    expect(Transaction::where('stripe_id', 'pi_prod_ordering')->exists())->toBeFalse();
});
