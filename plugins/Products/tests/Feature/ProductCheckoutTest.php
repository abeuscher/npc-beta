<?php

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Lifted from core's ProductPurchaseTest at the session-398 carve (rule-5
// membership: the subject is the plugin's ProductCheckoutController; the
// pure model capacity tests stayed core with the models).

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
