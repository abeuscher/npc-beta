<?php

use App\WidgetPrimitive\Slots\RecordDetailSidebarSlot;

it('declares the record-detail-sidebar identity and null config surface', function () {
    $slot = new RecordDetailSidebarSlot();

    expect($slot->handle())->toBe('record_detail_sidebar')
        ->and($slot->label())->toBe('Record Detail Sidebar')
        ->and($slot->configSurface())->toBeNull();
});

it('reports compact, column-stackable, bounded appearance constraints', function () {
    $constraints = (new RecordDetailSidebarSlot())->layoutConstraints();

    expect($constraints)->toBe([
        'allowed_appearance_fields' => ['background', 'text'],
        'dimensions'                => null,
        'column_stackable'          => true,
        'full_width_allowed'        => false,
    ]);
});

it('throws when ambientContext is called — wiring lands in Phase 5b', function () {
    (new RecordDetailSidebarSlot())->ambientContext();
})->throws(RuntimeException::class, 'Slot ambient context not yet wired — lands with Phase 5b');
