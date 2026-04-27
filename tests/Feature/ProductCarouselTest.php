<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Product model media collection ──────────────────────────────────────────

it('registers product_image media collection on Product model', function () {
    $product = Product::factory()->create();

    $collections = $product->getRegisteredMediaCollections();
    $names = collect($collections)->pluck('name')->all();

    expect($names)->toContain('product_image');
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
        ->toContain('success_page')
        ->not->toContain('background_color')
        ->not->toContain('text_color')
        ->not->toContain('full_width');
});

// ── Blade template rendering through the contract resolver ──────────────────

it('product carousel renders slides with product data and buy forms', function () {
    view()->share('errors', new ViewErrorBag());

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

    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $wt = WidgetType::where('handle', 'product_carousel')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Render Host', 'slug' => 'render-host', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => array_merge($wt->getDefaultConfig(), ['heading' => 'Our Products']),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)->toContain('Our Products')
        ->toContain('Test Widget Product')
        ->toContain('A test description for the carousel.')
        ->toContain('Basic')
        ->toContain('19.99')
        ->toContain('product_price_id')
        ->toContain('_token');
});

// ── Widget count update ────────────────────────────────────────────────────

it('seeder total widget count includes product_carousel', function () {
    $this->artisan('db:seed', ['--class' => 'WidgetTypeSeeder']);

    $widgets = WidgetType::all();
    expect($widgets)->toHaveCount(35);
});
