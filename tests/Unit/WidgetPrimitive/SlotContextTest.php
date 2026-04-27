<?php

use App\Models\Page;
use App\WidgetPrimitive\AmbientContexts\DashboardAmbientContext;
use App\WidgetPrimitive\AmbientContexts\PageAmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\SlotContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the Page when ambient is a PageAmbientContext carrying a Page', function () {
    $page = Page::factory()->create(['title' => 'Held Page']);

    $ctx = new SlotContext(new PageAmbientContext($page));

    expect($ctx->currentPage())->not->toBeNull()
        ->and($ctx->currentPage()->id)->toBe($page->id);
});

it('returns null when ambient is a PageAmbientContext carrying null', function () {
    $ctx = new SlotContext(new PageAmbientContext(null));

    expect($ctx->currentPage())->toBeNull();
});

it('returns null when ambient is a DashboardAmbientContext', function () {
    $ctx = new SlotContext(new DashboardAmbientContext());

    expect($ctx->currentPage())->toBeNull();
});

it('returns null when ambient is a RecordDetailAmbientContext', function () {
    $ctx = new SlotContext(new RecordDetailAmbientContext());

    expect($ctx->currentPage())->toBeNull();
});

it('defaults publicSurface to true and round-trips an explicit false', function () {
    $defaultCtx = new SlotContext(new PageAmbientContext());
    $adminCtx = new SlotContext(new DashboardAmbientContext(), publicSurface: false);

    expect($defaultCtx->publicSurface)->toBeTrue()
        ->and($adminCtx->publicSurface)->toBeFalse();
});
