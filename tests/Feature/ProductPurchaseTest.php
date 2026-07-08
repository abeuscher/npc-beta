<?php

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Capacity enforcement ──────────────────────────────────────────────────────

it('blocks checkout when product is at capacity', function () {
    $product = Product::factory()->create(['capacity' => 2]);
    $price   = ProductPrice::factory()->create([
        'product_id'     => $product->id,
        'amount'         => 50.00,
        'stripe_price_id' => null,
    ]);

    // Fill capacity
    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'status'           => 'active',
    ]);
    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'status'           => 'active',
    ]);

    config(['services.stripe.secret' => 'sk_test_fake']);

    $response = $this->post(route('products.checkout'), [
        'product_price_id' => $price->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('checkout');
});

it('allows checkout when product has remaining capacity', function () {
    $product = Product::factory()->create(['capacity' => 5]);
    $price   = ProductPrice::factory()->create([
        'product_id'      => $product->id,
        'amount'          => 50.00,
        'stripe_price_id' => 'price_test_123',
    ]);

    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'status'           => 'active',
    ]);

    expect($product->isAtCapacity())->toBeFalse();
});

it('correctly counts only active purchases for capacity', function () {
    $product = Product::factory()->create(['capacity' => 1]);
    $price   = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'amount'     => 10.00,
    ]);

    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'status'           => 'cancelled',
    ]);

    expect($product->isAtCapacity())->toBeFalse();

    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'status'           => 'active',
    ]);

    expect($product->fresh()->isAtCapacity())->toBeTrue();
});

// The former "Purchase creation", "ProductPriceObserver", and "Waitlist"
// sections were removed at the s364 test audit: the purchase test echoed its
// own factory attributes; the two observer tests passed identically with the
// observer unregistered (the factory never sets stripe_price_id and the
// observer early-returns on an empty Stripe key); the waitlist tests updated
// a plain fillable column and asserted Eloquent saved it. The real gaps —
// the observer's archive-and-recreate flow and ProductWaitlistController —
// are recorded in the housekeeping inbox, not papered over here.
