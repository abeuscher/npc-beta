<?php

use App\Models\DashboardConfig;
use App\Models\PageWidget;
use Database\Seeders\DashboardConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('creates a DashboardConfig for super_admin and seeds the three default widgets in order', function () {
    (new DashboardConfigSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();
    $config = DashboardConfig::where('role_id', $superAdmin->id)->first();

    expect($config)->not->toBeNull();

    $handles = $config->widgets()->with('widgetType')->orderBy('sort_order')->get()
        ->map(fn ($w) => $w->widgetType->handle)
        ->all();

    expect($handles)->toBe(['memos', 'quick_actions', 'this_weeks_events']);
});

it('is idempotent — running twice does not create a second config or duplicate widgets', function () {
    (new DashboardConfigSeeder())->run();
    (new DashboardConfigSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();

    expect(DashboardConfig::where('role_id', $superAdmin->id)->count())->toBe(1);

    $config = DashboardConfig::where('role_id', $superAdmin->id)->first();
    expect($config->widgets()->count())->toBe(3);
});

it('never clobbers an existing arrangement — no widgets seeded when the config already has widgets', function () {
    (new DashboardConfigSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();
    $config = DashboardConfig::where('role_id', $superAdmin->id)->first();

    $config->widgets()->delete();

    $memos = \App\Models\WidgetType::where('handle', 'memos')->first();
    PageWidget::create([
        'owner_type'        => $config->getMorphClass(),
        'owner_id'          => $config->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'Custom Memos',
        'config'            => ['limit' => 2],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    (new DashboardConfigSeeder())->run();

    expect($config->widgets()->count())->toBe(1)
        ->and($config->widgets()->first()->label)->toBe('Custom Memos');
});

it('is a no-op when the super_admin role does not exist', function () {
    Role::where('name', 'super_admin')->delete();

    (new DashboardConfigSeeder())->run();

    expect(DashboardConfig::count())->toBe(0);
});

it('seeded widgets ship with the default configs the hardcoded widgets() used in session 214', function () {
    (new DashboardConfigSeeder())->run();

    $superAdmin = Role::where('name', 'super_admin')->first();
    $config = DashboardConfig::where('role_id', $superAdmin->id)->first();

    $widgets = $config->widgets()->with('widgetType')->orderBy('sort_order')->get()
        ->keyBy(fn ($w) => $w->widgetType->handle);

    expect($widgets['memos']->config)->toBe(['limit' => 5])
        ->and($widgets['quick_actions']->config)->toBe(['actions' => ['new_contact', 'new_event', 'new_post']])
        ->and($widgets['this_weeks_events']->config)->toBe(['days_ahead' => 7]);
});
