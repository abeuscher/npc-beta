<?php

use App\Models\Contact;
use App\Models\PageWidget;
use App\WidgetPrimitive\IsView;
use App\WidgetPrimitive\Views\RecordDetailView;

it('round-trips handle, recordType, widgets, and layoutConfig via accessors', function () {
    $widgets = [new PageWidget(), new PageWidget()];
    $layoutConfig = ['columns' => 1];

    $view = new RecordDetailView(
        handle: 'contact_overview',
        recordType: Contact::class,
        widgets: $widgets,
        layoutConfig: $layoutConfig,
    );

    expect($view)->toBeInstanceOf(IsView::class)
        ->and($view->handle())->toBe('contact_overview')
        ->and($view->recordType())->toBe(Contact::class)
        ->and($view->widgets())->toBe($widgets)
        ->and($view->layoutConfig())->toBe($layoutConfig);
});

it('always returns record_detail_sidebar from slotHandle()', function () {
    $view = new RecordDetailView(
        handle: 'contact_overview',
        recordType: Contact::class,
        widgets: [],
    );

    expect($view->slotHandle())->toBe('record_detail_sidebar');
});

it('defaults layoutConfig to an empty array when omitted', function () {
    $view = new RecordDetailView(
        handle: 'contact_overview',
        recordType: Contact::class,
        widgets: [],
    );

    expect($view->layoutConfig())->toBe([]);
});
