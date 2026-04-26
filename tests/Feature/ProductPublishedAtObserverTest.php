<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sets published_at when a draft product transitions to published', function () {
    $product = Product::factory()->create([
        'status'      => 'draft',
        'is_archived' => false,
    ]);

    expect($product->published_at)->toBeNull();

    $product->update(['status' => 'published']);

    expect($product->published_at)->not->toBeNull()
        ->and($product->published_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('sets published_at when a product is created with status=published', function () {
    $product = Product::factory()->create([
        'status'      => 'published',
        'is_archived' => false,
    ]);

    expect($product->published_at)->not->toBeNull()
        ->and($product->published_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('does not set published_at on archived products even when published', function () {
    $product = Product::factory()->create([
        'status'      => 'published',
        'is_archived' => true,
    ]);

    expect($product->published_at)->toBeNull();
});

it('does not override an existing published_at on update', function () {
    $existing = now()->subDays(10);

    $product = Product::factory()->create([
        'status'       => 'published',
        'is_archived'  => false,
        'published_at' => $existing,
    ]);

    $product->update(['name' => 'Renamed Product']);

    $product->refresh();
    expect($product->published_at?->timestamp)->toBe($existing->timestamp);
});

it('does not set published_at on draft products', function () {
    $product = Product::factory()->create([
        'status'      => 'draft',
        'is_archived' => false,
    ]);

    expect($product->published_at)->toBeNull();

    $product->update(['name' => 'Still Draft']);

    $product->refresh();
    expect($product->published_at)->toBeNull();
});
