<?php

use App\Models\Page;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\WidgetType;
use App\Services\PageContext;
use App\Services\WidgetRenderer;
use App\Widgets\ProductDisplay\ProductDisplayDefinition;
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

it('projects only contract-declared fields onto the ProductDisplay single-row DTO with is_at_capacity aggregate', function () {
    $product = Product::factory()->create([
        'name'        => 'Capacity Test Product',
        'slug'        => 'capacity-test-product',
        'description' => 'Capacity sentinel description.',
        'status'      => 'published',
        'is_archived' => false,
        'capacity'    => 2,
    ]);

    $price1 = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Tier One',
        'amount'     => 19.99,
        'sort_order' => 0,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Tier Two',
        'amount'     => 29.99,
        'sort_order' => 1,
    ]);

    foreach (range(1, 3) as $i) {
        Purchase::factory()->create([
            'product_id'       => $product->id,
            'product_price_id' => $price1->id,
            'status'           => 'active',
        ]);
    }

    $contract = (new ProductDisplayDefinition())->dataContract(['product_slug' => 'capacity-test-product']);
    $context = new SlotContext(new PageContext(null), null);
    $dto = app(ContractResolver::class)->resolve([$contract], $context)[0];

    expect($dto)->toHaveKey('item')
        ->and($dto['item'])->not->toBeNull()
        ->and(array_keys($dto['item']))->toEqualCanonicalizing(['id', 'name', 'description', 'is_at_capacity', 'prices'])
        ->and($dto['item'])->not->toHaveKey('slug')
        ->and($dto['item'])->not->toHaveKey('image_url')
        ->and($dto['item'])->not->toHaveKey('capacity')
        ->and($dto['item']['is_at_capacity'])->toBeTrue()
        ->and($dto['item']['name'])->toBe('Capacity Test Product')
        ->and($dto['item']['description'])->toBe('Capacity sentinel description.')
        ->and($dto['item']['prices'])->toHaveCount(2)
        ->and(array_keys($dto['item']['prices'][0]))->toEqualCanonicalizing(['id', 'label', 'amount']);
});

it('renders ProductDisplay through the contract resolver only and short-circuits on missing slug', function () {
    $product = Product::factory()->create([
        'name'        => 'Real Product',
        'slug'        => 'real-product',
        'description' => 'A real product.',
        'status'      => 'published',
        'is_archived' => false,
        'capacity'    => 100,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'label'      => 'Standard',
        'amount'     => 50.00,
        'sort_order' => 0,
    ]);

    $wt = WidgetType::where('handle', 'product_display')->firstOrFail();
    $host = Page::factory()->create(['title' => 'Display Host', 'slug' => 'display-host', 'status' => 'published']);

    $pw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['product_slug' => 'real-product'],
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
            && str_contains($sql, '"slug"')
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
        ->and($html)->toContain('Real Product');

    $missingPw = $host->widgets()->create([
        'widget_type_id' => $wt->id,
        'config'         => ['product_slug' => 'does-not-exist'],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $context = new SlotContext(new PageContext(null), null);
    $missingContract = (new ProductDisplayDefinition())->dataContract(['product_slug' => 'does-not-exist']);
    $missingDto = app(ContractResolver::class)->resolve([$missingContract], $context)[0];
    $missingHtml = WidgetRenderer::render($missingPw)['html'];

    expect($missingDto)->toBe(['item' => null])
        ->and(trim((string) $missingHtml))->toBe('');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $emptyContract = (new ProductDisplayDefinition())->dataContract(['product_slug' => '']);
    $emptyDto = app(ContractResolver::class)->resolve([$emptyContract], $context)[0];
    $emptyQueries = DB::getQueryLog();
    DB::disableQueryLog();

    $emptyProductSelects = array_values(array_filter($emptyQueries, function ($q) {
        $sql = $q['query'];
        return str_starts_with($sql, 'select')
            && str_contains($sql, 'from "products"');
    }));

    expect($emptyDto)->toBe(['item' => null])
        ->and(count($emptyProductSelects))->toBe(0);
});
