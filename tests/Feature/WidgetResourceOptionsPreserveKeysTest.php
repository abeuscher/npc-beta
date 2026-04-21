<?php

use App\Http\Resources\WidgetPreviewResource;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('preserves numeric-keyed select options through the resource layer', function () {
    $wt = WidgetType::create([
        'handle'      => 'test_select_widget',
        'label'       => 'Test',
        'render_mode' => 'server',
        'config_schema' => [
            ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => ['1' => '1', '2' => '2', '3' => '3']],
        ],
    ]);

    $page = Page::factory()->create();

    $pw = PageWidget::create([
        'owner_type'     => Page::class,
        'owner_id'       => $page->id,
        'widget_type_id' => $wt->id,
        'label'          => 'Test block',
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $resolved = (new WidgetPreviewResource($pw))->resolve(Request::create('/'));
    $columnsField = collect($resolved['widget_type_config_schema'])
        ->firstWhere('key', 'columns');

    expect($columnsField['options'])->toBe(['1' => '1', '2' => '2', '3' => '3']);
});
