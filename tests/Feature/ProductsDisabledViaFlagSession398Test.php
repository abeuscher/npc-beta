<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\ProductsDisabledViaFlagTestCase;

uses(ProductsDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 398 (Plugin Architecture arc D8): runtime-disabled ≡ the removal
// mirror's strip-the-line state, produced by the per-install activation flag
// (contract surface 1). The installed superset stays intact; only the
// subtraction differs.

it('keeps all nine lines in config(plugins) — the installed superset is untouched', function () {
    expect(config('plugins'))->toContain(\Plugins\Products\ProductsServiceProvider::class)
        ->and(config('plugins'))->toHaveCount(9)
        ->and(config('plugin-activation.disabled'))->toBe(['products']);
});

it('does not load the Products provider', function () {
    expect(app()->providerIsLoaded(\Plugins\Products\ProductsServiceProvider::class))->toBeFalse();
});

it('drops both product routes and the admin resource routes', function () {
    expect(Route::has('products.checkout'))->toBeFalse()
        ->and(Route::has('products.waitlist'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.products.index'))->toBeFalse();
});

it('drops both product widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    expect($handles)->not->toContain('product_display')
        ->and($handles)->not->toContain('product_carousel');
});
