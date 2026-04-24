<?php

use App\Models\DashboardConfig;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('belongs to a Spatie role and exposes its widgets as a morphMany of PageWidget', function () {
    $role = Role::where('name', 'super_admin')->first();
    $config = DashboardConfig::create(['role_id' => $role->id]);

    $memos = WidgetType::where('handle', 'memos')->first();
    PageWidget::create([
        'owner_type'        => $config->getMorphClass(),
        'owner_id'          => $config->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'Memos',
        'config'            => ['limit' => 5],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    expect($config->role->id)->toBe($role->id)
        ->and($config->widgets()->count())->toBe(1)
        ->and($config->widgets->first()->widgetType->handle)->toBe('memos');
});

it('forUser(user) resolves the config for the user\'s sole role', function () {
    $role = Role::where('name', 'super_admin')->first();
    $config = DashboardConfig::create(['role_id' => $role->id]);

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    expect(DashboardConfig::forUser($user)?->id)->toBe($config->id);
});

it('forUser(user) returns null when the user has no roles', function () {
    $user = User::factory()->create();

    expect(DashboardConfig::forUser($user))->toBeNull();
});

it('forUser(user) returns null when the user\'s role has no dashboard config', function () {
    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    expect(DashboardConfig::forUser($user))->toBeNull();
});

it('forUser(user) picks the first role by ascending roles.id when the user has multiple roles', function () {
    $superAdmin = Role::where('name', 'super_admin')->first();
    $cmsEditor  = Role::where('name', 'cms_editor')->first();

    $lowerId = min($superAdmin->id, $cmsEditor->id);
    $expectedRoleId = $lowerId;

    $expectedConfig = DashboardConfig::create(['role_id' => $expectedRoleId]);

    $user = User::factory()->create();
    $user->assignRole('cms_editor');
    $user->assignRole('super_admin');

    expect(DashboardConfig::forUser($user)?->id)->toBe($expectedConfig->id);
});

it('forUser(null) returns null', function () {
    expect(DashboardConfig::forUser(null))->toBeNull();
});
