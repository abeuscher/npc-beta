<?php

use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Services\PageBuilderDataSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 398 (Plugin Architecture arc D8): the schema twin for the sixth
// squash-boundary redraw. The four product tables left core's dump for
// plugin-owned byte-faithful migrations — the second NO-SEQUENCES redraw
// (all four PKs are uuid; the Memberships category, recorded rather than
// skipped). On the full composition the tables exist, the RESTRICT purchase
// FKs enforce their blocks-product-delete behavior, and the core reads gated
// on route presence resolve normally.

it('creates all four product tables through the plugin-owned migrations', function () {
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('product_prices'))->toBeTrue()
        ->and(Schema::hasTable('purchases'))->toBeTrue()
        ->and(Schema::hasTable('waitlist_entries'))->toBeTrue();
});

it('registers the plugin migration path on the migrator', function () {
    // The provider's loadMigrationsFrom(__DIR__ …) resolves the path-repo
    // symlink to the in-repo path (the 395 nuance) — this assertion
    // re-points vendor-relative in the extracted copy at extraction.
    expect(app('migrator')->paths())->toContain(base_path('plugins/Products/database/migrations'));
});

it('generates uuid primary keys on real writes — the no-sequences category, recorded', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);

    expect($product->id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/')
        ->and($price->id)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
});

it('enforces the RESTRICT purchase FK — the constraint is the blocks-product-delete behavior', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);

    Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'status'           => 'active',
    ]);

    expect(fn () => \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('orphans the purchase on contact force-delete — the SET NULL FK travels as plain behavior', function () {
    $product = Product::factory()->create();
    $price   = ProductPrice::factory()->create(['product_id' => $product->id]);
    $contact = Contact::factory()->create();

    $purchase = Purchase::factory()->create([
        'product_id'       => $product->id,
        'product_price_id' => $price->id,
        'contact_id'       => $contact->id,
    ]);

    \Illuminate\Support\Facades\DB::table('contacts')->where('id', $contact->id)->delete();

    expect($purchase->fresh()->contact_id)->toBeNull();
});

it('resolves the products picker on the full composition', function () {
    Product::factory()->create([
        'name'   => 'Pickable Product',
        'slug'   => 'pickable-product',
        'status' => 'published',
    ]);

    expect(PageBuilderDataSources::resolve('products'))->toBe(['pickable-product' => 'Pickable Product']);
});
