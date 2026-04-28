<?php

use App\Filament\Pages\DashboardSettingsPage;
use App\Livewire\DashboardBuilder;
use App\WidgetPrimitive\Views\DashboardView;
use App\Models\User;
use Database\Seeders\DashboardViewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\MemosCollectionSeeder())->run();
    (new DashboardViewSeeder())->run();
});

it('renders for super_admin and includes the dashboard builder', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get(DashboardSettingsPage::getUrl());

    $response->assertOk();
    $response->assertSee('Dashboard View');
    $response->assertSee('page-builder--dashboard', false);
});

it('denies cms_editor access to the settings page', function () {
    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    $response = $this->actingAs($user)->get(DashboardSettingsPage::getUrl());

    expect($response->getStatusCode())->toBeIn([403, 404]);
});

it('breadcrumbs follow the Tools ▸ Dashboard View pattern', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $page = new DashboardSettingsPage();

    expect($page->getBreadcrumbs())->toBe(['Tools', 'Dashboard View']);
});

it('creating a dashboard for a role without one firstOrCreates a DashboardView row', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $cmsEditorRole = Role::where('name', 'cms_editor')->first();
    expect(DashboardView::where('role_id', $cmsEditorRole->id)->count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(DashboardSettingsPage::class, ['roleId' => (string) $cmsEditorRole->id])
        ->call('createConfigForSelectedRole');

    expect(DashboardView::where('role_id', $cmsEditorRole->id)->count())->toBe(1);
});

it('DashboardBuilder Livewire shell aborts 403 for users without manage_dashboard_config', function () {
    $user = User::factory()->create();
    $user->assignRole('cms_editor');

    $config = DashboardView::first();

    $this->actingAs($user);

    Livewire::test(DashboardBuilder::class, ['dashboardConfigId' => $config->id])
        ->assertStatus(403);
});

it('DashboardBuilder bootstrap data is dashboard-mode and scoped to the dashboard-builder API', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $config = DashboardView::first();

    $component = Livewire::test(DashboardBuilder::class, ['dashboardConfigId' => $config->id]);
    $data = $component->instance()->getBootstrapData();

    expect($data['mode'])->toBe('dashboard')
        ->and($data['owner_id'])->toBe($config->id)
        ->and($data['api_base_url'])->toContain('/api/dashboard-builder/configs/' . $config->id)
        ->and($data['api_lookup_url'])->toBe($data['api_base_url'])
        ->and($data['allowed_appearance_fields'])->toBe(['background', 'text'])
        ->and($data['allowed_widget_handles'])->toEqualCanonicalizing(['memos', 'quick_actions', 'this_weeks_events'])
        ->and($data['role_label'])->toBe('Super Admin');
});
