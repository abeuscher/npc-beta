<?php

use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\WidgetPrimitive\IsView;
use App\WidgetPrimitive\Views\DashboardView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('lives on the dashboard_views table', function () {
    expect((new DashboardView())->getTable())->toBe('dashboard_views');
});

it('belongs to a Spatie role and exposes its widgets as a morphMany of PageWidget', function () {
    $role = Role::where('name', 'super_admin')->first();
    $view = DashboardView::create(['role_id' => $role->id]);

    $memos = WidgetType::where('handle', 'memos')->first();
    PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'Memos',
        'config'            => ['limit' => 5],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    expect($view->role->id)->toBe($role->id)
        ->and($view->pageWidgets()->count())->toBe(1)
        ->and($view->pageWidgets->first()->widgetType->handle)->toBe('memos');
});

it('implements the IsView contract — handle/slotHandle/widgets/layoutConfig', function () {
    $role = Role::where('name', 'super_admin')->first();
    $view = DashboardView::create(['role_id' => $role->id]);

    $memos = WidgetType::where('handle', 'memos')->first();
    $quick = WidgetType::where('handle', 'quick_actions')->first();

    PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'widget_type_id'    => $quick->id,
        'label'             => 'Quick',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 1,
        'is_active'         => true,
    ]);

    PageWidget::create([
        'owner_type'        => $view->getMorphClass(),
        'owner_id'          => $view->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'Memos',
        'config'            => ['limit' => 5],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    expect($view)->toBeInstanceOf(IsView::class)
        ->and($view->handle())->toBe('super-admin')
        ->and($view->slotHandle())->toBe('dashboard_grid')
        ->and($view->layoutConfig())->toBe([])
        ->and(array_map(fn ($w) => $w->widgetType->handle, $view->widgets()))
            ->toBe(['memos', 'quick_actions']);
});

it('forUser(user) resolves the view for the user\'s sole role', function () {
    $role = Role::where('name', 'super_admin')->first();
    $view = DashboardView::create(['role_id' => $role->id]);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect(DashboardView::forUser($user)?->id)->toBe($view->id);
});

it('forUser(user) returns null when the user has no roles', function () {
    $user = User::factory()->create();

    expect(DashboardView::forUser($user))->toBeNull();
});

it('forUser(user) returns null when the user\'s role has no dashboard view', function () {
    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    expect(DashboardView::forUser($user))->toBeNull();
});

it('forUser(user) picks the first role by ascending roles.id when the user has multiple roles', function () {
    $superAdmin = Role::where('name', 'super_admin')->first();
    $cmsEditor  = Role::where('name', 'cms_editor')->first();

    $lowerId = min($superAdmin->id, $cmsEditor->id);
    $expectedRoleId = $lowerId;

    $expectedView = DashboardView::create(['role_id' => $expectedRoleId]);

    $user = User::factory()->create();
    $user->assignRole('cms_editor');
    $user->assignRole('super_admin');

    expect(DashboardView::forUser($user)?->id)->toBe($expectedView->id);
});

it('forUser(null) returns null', function () {
    expect(DashboardView::forUser(null))->toBeNull();
});
