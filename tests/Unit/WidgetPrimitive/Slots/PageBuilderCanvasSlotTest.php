<?php

use App\Models\Page;
use App\Services\PageContext;
use App\WidgetPrimitive\SlotContext;
use App\WidgetPrimitive\Slots\PageBuilderCanvasSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('declares the page-builder-canvas identity and surface', function () {
    $slot = new PageBuilderCanvasSlot();

    expect($slot->handle())->toBe('page_builder_canvas')
        ->and($slot->label())->toBe('Page Builder Canvas')
        ->and($slot->configSurface())->toBe('page_builder_vue');
});

it('reports permissive layout constraints', function () {
    $constraints = (new PageBuilderCanvasSlot())->layoutConstraints();

    expect($constraints)->toBe([
        'allowed_appearance_fields' => '*',
        'dimensions'                => null,
        'column_stackable'          => true,
        'full_width_allowed'        => true,
    ]);
});

it('builds a SlotContext from a PageContext and optional page override', function () {
    $page = Page::factory()->create(['title' => 'Override']);
    $pageContext = app(PageContext::class);

    $slot = new PageBuilderCanvasSlot();
    $ctx = $slot->ambientContext($pageContext, $page);

    expect($ctx)->toBeInstanceOf(SlotContext::class)
        ->and($ctx->currentPage())->not->toBeNull()
        ->and($ctx->currentPage()->id)->toBe($page->id);
});

it('builds a SlotContext without an override when none is given', function () {
    $pageContext = app(PageContext::class);

    $ctx = (new PageBuilderCanvasSlot())->ambientContext($pageContext);

    expect($ctx)->toBeInstanceOf(SlotContext::class)
        ->and($ctx->currentPage())->toBeNull();
});
