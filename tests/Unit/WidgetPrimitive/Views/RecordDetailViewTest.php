<?php

use App\Models\Contact;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\WidgetPrimitive\IsView;
use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('round-trips handle, recordType, label, sort_order, and layoutConfig via accessors', function () {
    $view = RecordDetailView::factory()->create([
        'handle'        => 'contact_overview',
        'record_type'   => Contact::class,
        'label'         => 'Overview',
        'sort_order'    => 3,
        'layout_config' => ['columns' => 1],
    ]);

    expect($view)->toBeInstanceOf(IsView::class)
        ->and($view->handle())->toBe('contact_overview')
        ->and($view->recordType())->toBe(Contact::class)
        ->and($view->label)->toBe('Overview')
        ->and($view->sort_order)->toBe(3)
        ->and($view->layoutConfig())->toBe(['columns' => 1]);
});

it('always returns record_detail_sidebar from slotHandle()', function () {
    $view = RecordDetailView::factory()->create();

    expect($view->slotHandle())->toBe('record_detail_sidebar');
});

it('defaults layoutConfig to an empty array when null', function () {
    $view = RecordDetailView::factory()->create(['layout_config' => null]);

    expect($view->layoutConfig())->toBe([]);
});

it('widgets() returns the polymorphic page_widgets rows ordered by sort_order, with widgetType eager-loaded', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $view = RecordDetailView::factory()->create();
    $wt = WidgetType::where('handle', 'recent_notes')->first();

    PageWidget::create([
        'owner_type' => $view->getMorphClass(),
        'owner_id'   => $view->getKey(),
        'widget_type_id' => $wt->id,
        'sort_order' => 1,
        'is_active'  => true,
        'config' => [], 'query_config' => [], 'appearance_config' => [],
    ]);
    PageWidget::create([
        'owner_type' => $view->getMorphClass(),
        'owner_id'   => $view->getKey(),
        'widget_type_id' => $wt->id,
        'sort_order' => 0,
        'is_active'  => true,
        'config' => [], 'query_config' => [], 'appearance_config' => [],
    ]);

    $widgets = $view->widgets();

    expect($widgets)->toHaveCount(2)
        ->and($widgets[0]->sort_order)->toBe(0)
        ->and($widgets[1]->sort_order)->toBe(1)
        ->and($widgets[0]->relationLoaded('widgetType'))->toBeTrue();
});

it('widgets() omits inactive page_widgets', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();

    $view = RecordDetailView::factory()->create();
    $wt = WidgetType::where('handle', 'recent_notes')->first();

    PageWidget::create([
        'owner_type' => $view->getMorphClass(),
        'owner_id'   => $view->getKey(),
        'widget_type_id' => $wt->id,
        'sort_order' => 0,
        'is_active'  => false,
        'config' => [], 'query_config' => [], 'appearance_config' => [],
    ]);

    expect($view->widgets())->toBe([]);
});
