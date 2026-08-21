<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Plugins\FormsDisabledViaFlagTestCase;

uses(FormsDisabledViaFlagTestCase::class, RefreshDatabase::class);

// Session 397 (Plugin Architecture arc D7): runtime-disabled ≡ the removal
// mirror's strip-the-line state, produced by the per-install activation flag
// (contract surface 1). The installed superset stays intact; only the
// subtraction differs.

it('keeps all nine lines in config(plugins) — the installed superset is untouched', function () {
    expect(config('plugins'))->toContain(\Plugins\Forms\FormsServiceProvider::class)
        ->and(config('plugins'))->toHaveCount(9)
        ->and(config('plugin-activation.disabled'))->toBe(['forms']);
});

it('does not load the Forms provider', function () {
    expect(app()->providerIsLoaded(\Plugins\Forms\FormsServiceProvider::class))->toBeFalse();
});

it('drops the forms.submit route and the admin resource routes', function () {
    expect(Route::has('forms.submit'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.forms.index'))->toBeFalse();
});

it('drops the web_form widget row on the next widgets:sync', function () {
    $this->artisan('widgets:sync')->assertSuccessful();

    expect(\App\Models\WidgetType::pluck('handle'))->not->toContain('web_form');
});
