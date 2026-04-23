<?php

use App\WidgetPrimitive\Slots\DashboardGridSlot;

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

it('throws when ambientContext is called — wiring lands in Phase 3', function () {
    (new DashboardGridSlot())->ambientContext();
})->throws(RuntimeException::class, 'Slot ambient context not yet wired — lands with Phase 3');
