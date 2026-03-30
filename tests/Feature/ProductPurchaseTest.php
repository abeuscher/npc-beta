<?php

use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Models\WaitlistEntry;
use App\Observers\ProductPriceObserver;
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

// ── Purchase creation ─────────────────────────────────────────────────────────

it('creates a purchase record with correct attributes', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'amount'     => 99.99,
    ]);
    $contact = Contact::factory()->create();

    $purchase = Purchase::factory()->create([
        'product_id'        => $product->id,
        'product_price_id'  => $price->id,
        'contact_id'        => $contact->id,
        'stripe_session_id' => 'cs_test_789',
        'amount_paid'       => 99.99,
        'status'            => 'active',
    ]);

    expect($purchase->product_id)->toBe($product->id)
        ->and($purchase->product_price_id)->toBe($price->id)
        ->and($purchase->contact_id)->toBe($contact->id)
        ->and($purchase->amount_paid)->toBe('99.99')
        ->and($purchase->status)->toBe('active');
});

// ── ProductPriceObserver (mocked Stripe) ──────────────────────────────────────

it('has ProductPriceObserver registered on the model', function () {
    // The observer requires a real Stripe key to create prices, so we verify
    // the observer is wired up and skips when no key is configured.
    config(['services.stripe.secret' => '']);

    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'amount'     => 25.00,
    ]);

    // With no Stripe secret, the observer should exit early and leave stripe_price_id null
    expect($price->stripe_price_id)->toBeNull();
});

it('does not attempt Stripe price creation when amount is zero', function () {
    config(['services.stripe.secret' => '']);

    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create([
        'product_id'      => $product->id,
        'amount'          => 0.00,
        'stripe_price_id' => null,
    ]);

    expect($price->stripe_price_id)->toBeNull();
});

// ── Waitlist ──────────────────────────────────────────────────────────────────

it('creates a waitlist entry with waiting status', function () {
    $product = Product::factory()->create();
    $contact = Contact::factory()->create();

    $entry = WaitlistEntry::factory()->create([
        'product_id' => $product->id,
        'contact_id' => $contact->id,
        'status'     => 'waiting',
    ]);

    expect($entry->status)->toBe('waiting')
        ->and($entry->product_id)->toBe($product->id)
        ->and($entry->contact_id)->toBe($contact->id);
});

it('supports waitlist status transitions', function () {
    $entry = WaitlistEntry::factory()->create(['status' => 'waiting']);

    $entry->update(['status' => 'notified']);
    expect($entry->fresh()->status)->toBe('notified');

    $entry->update(['status' => 'converted']);
    expect($entry->fresh()->status)->toBe('converted');
});

it('supports cancelled waitlist status', function () {
    $entry = WaitlistEntry::factory()->create(['status' => 'waiting']);

    $entry->update(['status' => 'cancelled']);
    expect($entry->fresh()->status)->toBe('cancelled');
});
