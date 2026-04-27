<?php

use App\Models\Contact;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\WidgetPrimitive\Views\RecordDetailView;
use Database\Seeders\RecordDetailViewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new RecordDetailViewSeeder())->run();
});

it('deleting a View cascades its polymorphic page_widgets and page_layouts', function () {
    $view = RecordDetailView::create([
        'record_type' => Contact::class,
        'handle'      => 'contact_cascade_test',
        'label'       => 'Cascade Test',
        'sort_order'  => 9,
    ]);

    $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();

    $rootWidget = PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'widget_type_id'    => $placeholder->id,
        'label'             => 'Root',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $layout = PageLayout::create([
        'owner_type'    => $view->getMorphClass(),
        'owner_id'      => $view->getKey(),
        'label'         => 'Two Cols',
        'display'       => 'grid',
        'columns'       => 2,
        'layout_config' => ['grid_template_columns' => '1fr 1fr', 'gap' => '1.5rem'],
        'sort_order'    => 1,
    ]);

    $columnWidget = PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'layout_id'         => $layout->id,
        'column_index'      => 0,
        'widget_type_id'    => $placeholder->id,
        'label'             => 'In Column',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $view->delete();

    expect(PageWidget::find($rootWidget->id))->toBeNull()
        ->and(PageWidget::find($columnWidget->id))->toBeNull()
        ->and(PageLayout::find($layout->id))->toBeNull();
});
