<?php

use App\Models\PageWidget;
use App\WidgetPrimitive\Views\DashboardView;
use Database\Seeders\DashboardViewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('creates a DashboardView for super_admin and seeds the five default widgets in order', function () {
    (new DashboardViewSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();
    $view = DashboardView::where('role_id', $superAdmin->id)->first();

    expect($view)->not->toBeNull();

    $handles = $view->pageWidgets()->with('widgetType')->orderBy('sort_order')->get()
        ->map(fn ($w) => $w->widgetType->handle)
        ->all();

    expect($handles)->toBe(['setup_checklist', 'memos', 'quick_actions', 'this_weeks_events', 'random_data_generator']);
});

it('is idempotent — running twice does not create a second view or duplicate widgets', function () {
    (new DashboardViewSeeder())->run();
    (new DashboardViewSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();

    expect(DashboardView::where('role_id', $superAdmin->id)->count())->toBe(1);

    $view = DashboardView::where('role_id', $superAdmin->id)->first();
    expect($view->pageWidgets()->count())->toBe(5);
});

it('never clobbers an existing arrangement — no widgets seeded when the view already has widgets', function () {
    (new DashboardViewSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();
    $view = DashboardView::where('role_id', $superAdmin->id)->first();

    $view->pageWidgets()->delete();

    $memos = \App\Models\WidgetType::where('handle', 'memos')->first();
    PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'Custom Memos',
        'config'            => ['limit' => 2],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    (new DashboardViewSeeder())->run();

    expect($view->pageWidgets()->count())->toBe(1)
        ->and($view->pageWidgets()->first()->label)->toBe('Custom Memos');
});

it('is a no-op when the super_admin role does not exist', function () {
    Role::where('name', 'super_admin')->delete();

    (new DashboardViewSeeder())->run();

    expect(DashboardView::count())->toBe(0);
});

it('seeded widgets ship with the default configs the hardcoded widgets() used in session 214', function () {
    (new DashboardViewSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();
    $view = DashboardView::where('role_id', $superAdmin->id)->first();

    $widgets = $view->pageWidgets()->with('widgetType')->orderBy('sort_order')->get()
        ->keyBy(fn ($w) => $w->widgetType->handle);

    expect($widgets['memos']->config)->toBe(['limit' => 5])
        ->and($widgets['quick_actions']->config)->toBe(['actions' => ['new_contact', 'new_event', 'new_post']])
        ->and($widgets['this_weeks_events']->config)->toBe(['days_ahead' => 7]);
});
