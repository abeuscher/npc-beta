<?php

use App\Models\Event;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('backfills events.published_at from created_at for published rows', function () {
    $createdAt = now()->subDays(30);

    $event = Event::factory()->create([
        'status'    => 'published',
        'starts_at' => now()->addDay(),
    ]);

    DB::table('events')->where('id', $event->id)->update([
        'created_at'   => $createdAt,
        'published_at' => null,
    ]);

    DB::statement("UPDATE events SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");

    $event->refresh();
    expect($event->published_at?->timestamp)->toBe($createdAt->timestamp);
});

it('does not backfill draft events', function () {
    $event = Event::factory()->draft()->create([
        'starts_at' => now()->addDay(),
    ]);

    DB::table('events')->where('id', $event->id)->update(['published_at' => null]);

    DB::statement("UPDATE events SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");

    $event->refresh();
    expect($event->published_at)->toBeNull();
});

it('backfill is idempotent — does not overwrite existing published_at on re-run', function () {
    $existing = now()->subDays(5);

    $event = Event::factory()->create([
        'status'       => 'published',
        'starts_at'    => now()->addDay(),
        'published_at' => $existing,
    ]);

    DB::statement("UPDATE events SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");

    $event->refresh();
    expect($event->published_at?->timestamp)->toBe($existing->timestamp);
});

it('backfills products.published_at from created_at for published, non-archived rows', function () {
    $createdAt = now()->subDays(20);

    $product = Product::factory()->create([
        'status'      => 'published',
        'is_archived' => false,
    ]);

    DB::table('products')->where('id', $product->id)->update([
        'created_at'   => $createdAt,
        'published_at' => null,
    ]);

    DB::statement("UPDATE products SET published_at = created_at WHERE status = 'published' AND is_archived = false AND published_at IS NULL");

    $product->refresh();
    expect($product->published_at?->timestamp)->toBe($createdAt->timestamp);
});

it('does not backfill archived products', function () {
    $product = Product::factory()->create([
        'status'      => 'published',
        'is_archived' => true,
    ]);

    DB::table('products')->where('id', $product->id)->update(['published_at' => null]);

    DB::statement("UPDATE products SET published_at = created_at WHERE status = 'published' AND is_archived = false AND published_at IS NULL");

    $product->refresh();
    expect($product->published_at)->toBeNull();
});

it('does not backfill draft products', function () {
    $product = Product::factory()->create([
        'status'      => 'draft',
        'is_archived' => false,
    ]);

    DB::table('products')->where('id', $product->id)->update(['published_at' => null]);

    DB::statement("UPDATE products SET published_at = created_at WHERE status = 'published' AND is_archived = false AND published_at IS NULL");

    $product->refresh();
    expect($product->published_at)->toBeNull();
});
