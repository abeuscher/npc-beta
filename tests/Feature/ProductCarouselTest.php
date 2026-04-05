<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\WidgetType;
use App\Services\WidgetDataResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Product model media collection ──────────────────────────────────────────

it('registers product_image media collection on Product model', function () {
    $product = Product::factory()->create();

    $collections = $product->getRegisteredMediaCollections();
    $names = collect($collections)->pluck('name')->all();

    expect($names)->toContain('product_image');
});

// ── WidgetDataResolver::resolveProducts ─────────────────────────────────────

it('resolveProducts returns image_url and prices array', function () {
    $product = Product::factory()->create([
        'status'      => 'published',
        'is_archived' => false,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Standard',
        'amount'     => 29.99,
        'sort_order' => 0,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Premium',
        'amount'     => 59.99,
        'sort_order' => 1,
    ]);

    $result = WidgetDataResolver::resolveProducts();

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveKeys(['id', 'name', 'slug', 'description', 'capacity', 'available', 'image_url', 'prices'])
        ->and($result[0]['prices'])->toHaveCount(2)
        ->and($result[0]['prices'][0])->toHaveKeys(['id', 'label', 'amount', 'stripe_price_id'])
        ->and($result[0]['prices'][0]['label'])->toBe('Standard')
        ->and($result[0]['prices'][1]['label'])->toBe('Premium');
});

it('resolveProducts excludes draft and archived products', function () {
    Product::factory()->create(['status' => 'draft', 'is_archived' => false]);
    Product::factory()->create(['status' => 'published', 'is_archived' => true]);
    Product::factory()->create(['status' => 'published', 'is_archived' => false]);

    $result = WidgetDataResolver::resolveProducts();

    expect($result)->toHaveCount(1);
});

it('resolveProducts respects limit parameter', function () {
    foreach (range(1, 5) as $i) {
        Product::factory()->create([
            'status'      => 'published',
            'is_archived' => false,
            'sort_order'  => $i,
        ]);
    }

    $result = WidgetDataResolver::resolveProducts(['limit' => 3]);

    expect($result)->toHaveCount(3);
});

// ── ProductCheckoutController success_page ──────────────────────────────────

it('validates success_page as an existing page slug', function () {
    $product = Product::factory()->create(['capacity' => 10]);
    $price   = ProductPrice::factory()->create([
        'product_id'      => $product->id,
        'amount'          => 25.00,
        'stripe_price_id' => null,
    ]);

    config(['services.stripe.secret' => 'sk_test_fake']);

    $response = $this->post(route('products.checkout'), [
        'product_price_id' => $price->id,
        'success_page'     => 'nonexistent-page-slug',
    ]);

    $response->assertSessionHasErrors('success_page');
});

it('accepts a valid success_page slug', function () {
    $product = Product::factory()->create(['capacity' => 10]);
    $price   = ProductPrice::factory()->create([
        'product_id'      => $product->id,
        'amount'          => 25.00,
        'stripe_price_id' => 'price_test_123',
    ]);

    Page::factory()->create(['slug' => 'thank-you', 'status' => 'published']);

    config(['services.stripe.secret' => 'sk_test_fake']);

    // The Stripe call will fail, but we expect the validation to pass (no success_page error)
    $response = $this->post(route('products.checkout'), [
        'product_price_id' => $price->id,
        'success_page'     => 'thank-you',
    ]);

    $response->assertSessionDoesntHaveErrors('success_page');
});

// ── Widget type seeder ──────────────────────────────────────────────────────

it('seeder creates product_carousel with correct config schema', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $wt = WidgetType::where('handle', 'product_carousel')->first();

    expect($wt)->not->toBeNull()
        ->and($wt->label)->toBe('Product Carousel')
        ->and($wt->category)->toContain('giving_and_sales')
        ->and($wt->collections)->toBe([]);

    $keys = collect($wt->config_schema)->pluck('key')->all();

    expect($keys)->toContain('heading')
        ->toContain('limit')
        ->toContain('navigation')
        ->toContain('pagination')
        ->toContain('autoplay')
        ->toContain('interval')
        ->toContain('background_color')
        ->toContain('text_color')
        ->toContain('success_page')
        ->toContain('full_width');
});

// ── ProductDemoSeeder ───────────────────────────────────────────────────────

it('ProductDemoSeeder creates products with prices and images', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductDemoSeeder']);

    $products = Product::where('status', 'published')->get();

    expect($products)->toHaveCount(5);

    foreach ($products as $product) {
        expect($product->prices)->not->toBeEmpty("Product '{$product->name}' should have at least one price tier");
    }
});

// ── Blade template rendering ────────────────────────────────────────────────

it('product carousel blade renders slides with product data and buy forms', function () {
    $product = Product::factory()->create([
        'name'        => 'Test Widget Product',
        'description' => 'A test description for the carousel.',
        'status'      => 'published',
        'is_archived' => false,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Basic',
        'amount'     => 19.99,
        'sort_order' => 0,
    ]);

    $html = view('widgets.product-carousel', [
        'config'         => ['heading' => 'Our Products'],
        'configMedia'    => [],
        'collectionData' => [],
        'pageContext'    => app(\App\Services\PageContext::class),
    ])->render();

    expect($html)->toContain('Our Products')
        ->toContain('Test Widget Product')
        ->toContain('A test description for the carousel.')
        ->toContain('Basic')
        ->toContain('19.99')
        ->toContain('product_price_id')
        ->toContain('_token');
});

// ── Widget count update ──────────────────────────────────────────���──────────

it('seeder total widget count includes product_carousel', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $widgets = WidgetType::all();
    expect($widgets)->toHaveCount(29);
});
