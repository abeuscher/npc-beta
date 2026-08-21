<?php

use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Payments\Events\CheckoutSettled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\ProductsRemovedTestCase;

uses(ProductsRemovedTestCase::class, RefreshDatabase::class);

// Session 398 (Plugin Architecture arc D8): the remove-the-line mirror for
// the Products vertical. The application boots with the Products line
// stripped from config('plugins'); both routes, both widgets, the admin
// surface, both observers, and the purchase-fulfillment listener must vanish
// — while product DATA is kept (disabled ≠ inert, contract surface 5).

it('does not load the Products provider', function () {
    expect(config('plugins'))->not->toContain(\Plugins\Products\ProductsServiceProvider::class)
        ->and(app()->providerIsLoaded(\Plugins\Products\ProductsServiceProvider::class))->toBeFalse();
});

it('drops both product routes — the POSTs answer 405 from the GET catch-all', function () {
    expect(Route::has('products.checkout'))->toBeFalse()
        ->and(Route::has('products.waitlist'))->toBeFalse();

    $this->post('/products/checkout')->assertStatus(405);
    $this->post('/products/waitlist')->assertStatus(405);
});

it('drops the three ProductResource admin routes', function () {
    foreach ([
        'filament.admin.resources.products.index',
        'filament.admin.resources.products.create',
        'filament.admin.resources.products.edit',
    ] as $name) {
        expect(Route::has($name))->toBeFalse("route {$name} should be gone");
    }
});

it('drops both product widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    expect($handles)->not->toContain('product_display')
        ->and($handles)->not->toContain('product_carousel');
});

it('keeps product data queryable — disabled is inert, never destructive', function () {
    $product = Product::factory()->create(['capacity' => 5]);
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);

    Purchase::create([
        'product_id'        => $product->id,
        'product_price_id'  => $price->id,
        'contact_id'        => null,
        'stripe_session_id' => 'cs_test_kept_398',
        'amount_paid'       => 10.00,
        'status'            => 'active',
        'occurred_at'       => now(),
    ]);

    expect(Product::count())->toBe(1)
        ->and(Purchase::count())->toBe(1)
        ->and($product->purchases()->count())->toBe(1);
});

it('does not stamp published_at on publish-create — the ProductObserver is detached', function () {
    $product = Product::create([
        'name'     => 'Unstamped',
        'slug'     => 'unstamped-398',
        'status'   => 'published',
        'capacity' => 10,
    ]);

    expect($product->published_at)->toBeNull();
});

it('logs no purchase activity on create — the PurchaseObserver is detached', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);
    $contact = Contact::factory()->create();

    Purchase::create([
        'product_id'        => $product->id,
        'product_price_id'  => $price->id,
        'contact_id'        => $contact->id,
        'stripe_session_id' => 'cs_test_no_activity_398',
        'amount_paid'       => 25.00,
        'status'            => 'active',
        'occurred_at'       => now(),
    ]);

    expect(\App\Models\ActivityLog::where('event', 'purchased')->exists())->toBeFalse();
});

it('leaves a product-tagged CheckoutSettled dispatch with no listener — no purchase row, data kept', function () {
    $product = Product::factory()->create(['capacity' => 10]);
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);

    event(new CheckoutSettled((object) [
        'id'               => 'cs_test_no_listener_398',
        'payment_intent'   => 'pi_no_listener_398',
        'amount_total'     => 3500,
        'customer_details' => (object) ['email' => 'nolistener398@example.com', 'name' => 'No Listener'],
    ], (object) ['product_price_id' => $price->id]));

    expect(Purchase::count())->toBe(0)
        ->and(Contact::where('email', 'nolistener398@example.com')->exists())->toBeFalse()
        ->and(Product::count())->toBe(1);
});

it('degrades the products page-builder picker to an empty list', function () {
    Product::factory()->create(['status' => 'published']);

    expect(\App\Services\PageBuilderDataSources::resolve('products'))->toBe([]);
});
