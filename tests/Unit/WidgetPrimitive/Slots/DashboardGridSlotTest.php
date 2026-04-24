<?php

use App\WidgetPrimitive\SlotContext;
use App\WidgetPrimitive\Slots\DashboardGridSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('declares the dashboard-grid identity and null config surface', function () {
    $slot = new DashboardGridSlot();

    expect($slot->handle())->toBe('dashboard_grid')
        ->and($slot->label())->toBe('Dashboard Grid')
        ->and($slot->configSurface())->toBeNull();
});

it('reports grid-cell dimensions and bounded appearance', function () {
    $constraints = (new DashboardGridSlot())->layoutConstraints();

    expect($constraints)->toBe([
        'allowed_appearance_fields' => ['background', 'text'],
        'dimensions'                => ['width' => 'int', 'height' => 'int'],
        'column_stackable'          => false,
        'full_width_allowed'        => false,
    ]);
});

it('builds a SlotContext with a null current page', function () {
    $ctx = (new DashboardGridSlot())->ambientContext();

    expect($ctx)->toBeInstanceOf(SlotContext::class)
        ->and($ctx->currentPage())->toBeNull();
});

it('builds a SlotContext marked publicSurface = false — admin-only surface', function () {
    $ctx = (new DashboardGridSlot())->ambientContext();

    expect($ctx->publicSurface)->toBeFalse();
});
