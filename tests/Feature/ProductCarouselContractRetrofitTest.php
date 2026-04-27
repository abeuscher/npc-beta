<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\WidgetType;
use App\Services\WidgetRenderer;
use App\Widgets\ProductCarousel\ProductCarouselDefinition;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    view()->share('errors', new ViewErrorBag());
});

it('projects only contract-declared fields onto ProductCarousel rows with nested prices DTO (fail-closed whitelist)', function () {
    foreach (range(0, 2) as $i) {
        $product = Product::factory()->create([
            'name'        => "Whitelisted Product $i",
            'description' => "Description for product $i",
            'status'      => 'published',
            'is_archived' => false,
            'sort_order'  => $i,
        ]);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'label'      => "Tier A-$i",
            'amount'     => 19.99,
            'sort_order' => 0,
        ]);

        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'label'      => "Tier B-$i",
            'amount'     => 29.99,
            'sort_order' => 1,
        ]);
    }

    $contract = (new ProductCarouselDefinition())->dataContract([]);
    $context = new SlotContext(new PageAmbientContext());
    $dto = app(ContractResolver::class)->resolve([$contract], $context)[0];

    expect($dto)->toHaveKey('items')
        ->and($dto['items'])->toHaveCount(3)
        ->and(array_keys($dto['items'][0]))->toEqualCanonicalizing(['id', 'name', 'description', 'image_url', 'prices'])
        ->and($dto['items'][0])->not->toHaveKey('slug')
        ->and($dto['items'][0])->not->toHaveKey('capacity')
        ->and($dto['items'][0])->not->toHaveKey('is_at_capacity')
        ->and($dto['items'][0]['prices'])->toHaveCount(2)
        ->and(array_keys($dto['items'][0]['prices'][0]))->toEqualCanonicalizing(['id', 'label', 'amount']);

    $wt = WidgetType::where('handle', 'product_carousel')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Carousel Host', 'slug' => 'carousel-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => $wt->getDefaultConfig(),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $html = WidgetRenderer::render($pw)['html'];

    expect($html)
        ->toContain('Whitelisted Product 0')
        ->toContain('Whitelisted Product 1')
        ->toContain('Whitelisted Product 2')
        ->toContain('Tier A-0')
        ->toContain('Tier B-0')
        ->toContain('product_price_id');
});

it('renders ProductCarousel through the contract resolver only with withCount as the only path to active_purchases_count', function () {
    $published = Product::factory()->create([
        'name'        => 'Published Product',
        'status'      => 'published',
        'is_archived' => false,
        'sort_order'  => 0,
        'capacity'    => 10,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $published->id,
        'label'      => 'Standard',
        'amount'     => 19.99,
        'sort_order' => 0,
    ]);

    foreach (range(1, 3) as $i) {
        Purchase::factory()->create([
            'product_id'       => $published->id,
            'product_price_id' => $published->prices()->first()->id,
            'status'           => 'active',
        ]);
    }

    Product::factory()->create([
        'name'        => 'Draft Product Should Not Render',
        'status'      => 'draft',
        'is_archived' => false,
        'sort_order'  => 1,
    ]);

    Product::factory()->create([
        'name'        => 'Archived Product Should Not Render',
        'status'      => 'published',
        'is_archived' => true,
        'sort_order'  => 2,
    ]);

    $wt = WidgetType::where('handle', 'product_carousel')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Carousel Query Host', 'slug' => 'carousel-query-host', 'status' => 'published']);
    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => $wt->getDefaultConfig(),
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    DB::enableQueryLog();
    $html = WidgetRenderer::render($pw)['html'];
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $productSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "products"')
            && str_contains($sql, '"status"')
            && str_contains($sql, '"is_archived"')
            && str_contains($sql, 'as "active_purchases_count"');
    }));

    $mediaSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "media"')));

    $priceSelects = array_values(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "product_prices"')));

    $standalonePurchaseSelects = array_values(array_filter($queries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "purchases"')
            && ! str_contains($sql, 'from "products"');
    }));

    expect(count($productSelects))->toBe(1)
        ->and(count($mediaSelects))->toBe(1)
        ->and(count($priceSelects))->toBe(1)
        ->and(count($standalonePurchaseSelects))->toBe(0)
        ->and(substr_count($html, 'class="swiper-slide product-slide"'))->toBe(1)
        ->and($html)->toContain('Published Product')
        ->and($html)->not->toContain('Draft Product Should Not Render')
        ->and($html)->not->toContain('Archived Product Should Not Render');
});
