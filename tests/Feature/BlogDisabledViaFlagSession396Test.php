<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\BlogDisabledViaFlagTestCase;

uses(BlogDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 396 (Plugin Architecture arc D6): runtime-disabled ≡ the removal
// mirror's strip-the-line state, produced by the per-install activation flag
// (contract surface 1). The installed superset stays intact; only the
// subtraction differs.

it('keeps all eight lines in config(plugins) — the installed superset is untouched', function () {
    expect(config('plugins'))->toContain(\Plugins\Blog\BlogServiceProvider::class)
        ->and(config('plugins'))->toHaveCount(8)
        ->and(config('plugin-activation.disabled'))->toBe(['blog']);
});

it('does not load the Blog provider', function () {
    expect(app()->providerIsLoaded(\Plugins\Blog\BlogServiceProvider::class))->toBeFalse();
});

it('drops the named blog routes and the admin resource routes', function () {
    expect(Route::has('posts.index'))->toBeFalse()
        ->and(Route::has('posts.show'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.posts.index'))->toBeFalse();
});

it('drops both blog widget rows on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    $handles = \App\Models\WidgetType::pluck('handle');

    expect($handles)->not->toContain('blog_listing')
        ->and($handles)->not->toContain('blog_pager');
});
