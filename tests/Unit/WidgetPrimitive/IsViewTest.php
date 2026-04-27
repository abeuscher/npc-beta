<?php

use App\WidgetPrimitive\IsView;

it('declares the four IsView contract methods on a top-level interface', function () {
    $reflection = new ReflectionClass(IsView::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->getNamespaceName())->toBe('App\\WidgetPrimitive')
        ->and($reflection->hasMethod('handle'))->toBeTrue()
        ->and($reflection->hasMethod('slotHandle'))->toBeTrue()
        ->and($reflection->hasMethod('widgets'))->toBeTrue()
        ->and($reflection->hasMethod('layoutConfig'))->toBeTrue();
});
