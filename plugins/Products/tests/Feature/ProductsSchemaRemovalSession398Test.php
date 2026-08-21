<?php

use App\Models\Product;
use App\Services\PageBuilderDataSources;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\ContractResolver;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Plugins\ProductsRemovedTestCase;

uses(ProductsRemovedTestCase::class, RefreshDatabase::class);

// Session 398 (Plugin Architecture arc D8): the schema removal mirror.
// Disabled ≠ uninstalled (contract surface 5): the fixture registers the
// plugin's migration path directly, so the four product tables exist and
// data is kept — while the route-presence-gated core reads prove they gate
// against LIVE rows, not an empty table.

it('keeps all four product tables via the fixture migrator path', function () {
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('product_prices'))->toBeTrue()
        ->and(Schema::hasTable('purchases'))->toBeTrue()
        ->and(Schema::hasTable('waitlist_entries'))->toBeTrue();
});

it('returns an empty products picker even with a published row present — the gate, not an empty table', function () {
    Product::factory()->create(['status' => 'published']);

    expect(PageBuilderDataSources::resolve('products'))->toBe([]);
});

it('resolves the product list arm to empty and the single arm to null against live rows', function () {
    Product::factory()->create([
        'name'   => 'Live Product',
        'slug'   => 'live-product',
        'status' => 'published',
    ]);

    $context = new SlotContext(new PageAmbientContext());

    $listContract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['id', 'name'],
        filters: [],
        model: 'product',
    );
    $oneContract = new DataContract(
        version: '1.0.0',
        source: DataContract::SOURCE_SYSTEM_MODEL,
        fields: ['id', 'name'],
        filters: ['slug' => 'live-product'],
        model: 'product',
        cardinality: DataContract::CARDINALITY_ONE,
    );

    $resolver = app(ContractResolver::class);

    expect($resolver->resolve([$listContract], $context)[0]['items'])->toBe([])
        ->and($resolver->resolve([$oneContract], $context)[0]['item'])->toBeNull();
});

it('exports an empty products slice — the manifest pluck gates on route presence', function () {
    Product::factory()->create(['status' => 'published']);

    $payload = app(\App\Services\ImportExport\ContentExporter::class)->exportSite([
        'with_design' => false,
        'with_media'  => false,
    ]);

    expect($payload['payload']['products'])->toBe([]);
});
