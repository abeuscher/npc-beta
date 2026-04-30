<?php

use App\WidgetPrimitive\Views\DashboardView;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Database\Seeders\DashboardViewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\MemosCollectionSeeder())->run();
    (new DashboardViewSeeder())->run();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->cmsEditor = User::factory()->create();
    $this->cmsEditor->assignRole('cms_editor');

    $this->config = DashboardView::first();
});

function dashboardUrl(string $configId, string $path = ''): string
{
    $base = '/admin/api/dashboard-builder/configs/' . $configId;
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

// ── Authorization ───────────────────────────────────────────────────────────

it('returns 403 on index for a user without manage_dashboard_config', function () {
    $this->actingAs($this->cmsEditor)
        ->getJson(dashboardUrl($this->config->id, 'widgets'))
        ->assertStatus(403);
});

it('returns 200 on index for a super_admin (gate bypass)', function () {
    $this->actingAs($this->superAdmin)
        ->getJson(dashboardUrl($this->config->id, 'widgets'))
        ->assertOk()
        ->assertJsonStructure(['items', 'required_libs']);
});

it('rejects widget creation from a user without the permission', function () {
    $memos = WidgetType::where('handle', 'memos')->first();

    $this->actingAs($this->cmsEditor)
        ->postJson(dashboardUrl($this->config->id, 'widgets'), [
            'widget_type_id' => $memos->id,
        ])
        ->assertStatus(403);
});

// ── allowedSlots enforcement ────────────────────────────────────────────────

it('rejects widget creation when the widget handle does not allow dashboard_grid', function () {
    $notDashboard = WidgetType::whereNotIn('handle', ['memos', 'quick_actions', 'this_weeks_events'])->first();

    $response = $this->actingAs($this->superAdmin)
        ->postJson(dashboardUrl($this->config->id, 'widgets'), [
            'widget_type_id' => $notDashboard->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => "Widget [{$notDashboard->handle}] is not allowed in the dashboard grid slot."]);

    expect(PageWidget::where('widget_type_id', $notDashboard->id)->count())->toBe(0);
});

it('accepts widget creation for handles whose allowedSlots() includes dashboard_grid', function () {
    $this->config->pageWidgets()->delete();

    $memos = WidgetType::where('handle', 'memos')->first();

    $response = $this->actingAs($this->superAdmin)
        ->postJson(dashboardUrl($this->config->id, 'widgets'), [
            'widget_type_id' => $memos->id,
        ]);

    $response->assertCreated();
    expect($this->config->pageWidgets()->count())->toBe(1);
});

// ── IDOR guard ──────────────────────────────────────────────────────────────

it('returns 404 when updating a widget that belongs to a different dashboard config', function () {
    $otherRole = Role::create(['name' => 'other_role', 'guard_name' => 'web']);
    $otherConfig = DashboardView::create(['role_id' => $otherRole->id]);

    $memos = WidgetType::where('handle', 'memos')->first();
    $otherWidget = PageWidget::create([
        'owner_type'        => $otherConfig->getMorphClass(),
        'owner_id'          => $otherConfig->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'Other',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->putJson(dashboardUrl($this->config->id, "widgets/{$otherWidget->id}"), [
            'label' => 'Hijacked',
        ])
        ->assertStatus(404);

    expect($otherWidget->fresh()->label)->toBe('Other');
});

it('returns 404 when the widget belongs to a Page (not a DashboardView) via the dashboard route', function () {
    $page = \App\Models\Page::factory()->create();
    $memos = WidgetType::where('handle', 'memos')->first();
    $pageWidget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $memos->id,
        'label'             => 'On a page',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->deleteJson(dashboardUrl($this->config->id, "widgets/{$pageWidget->id}"))
        ->assertStatus(404);

    expect(PageWidget::find($pageWidget->id))->not->toBeNull();
});

// ── Appearance whitelist ───────────────────────────────────────────────────

it('strips appearance_config keys outside background/text on update', function () {
    $widget = $this->config->pageWidgets()->first();

    $this->actingAs($this->superAdmin)
        ->putJson(dashboardUrl($this->config->id, "widgets/{$widget->id}"), [
            'appearance_config' => [
                'background' => ['color' => '#ff0000'],
                'text'       => ['color' => '#00ff00'],
                'layout'     => ['full_width' => true, 'padding' => ['top' => 99]],
            ],
        ])
        ->assertOk();

    $updated = $widget->fresh()->appearance_config;

    expect($updated)->toHaveKey('background')
        ->and($updated)->toHaveKey('text')
        ->and($updated)->not->toHaveKey('layout');
});

// ── Reorder / delete happy paths ───────────────────────────────────────────

it('reorders widgets within a dashboard config', function () {
    $ids = $this->config->pageWidgets()->orderBy('sort_order')->pluck('id')->all();

    $reversed = array_reverse($ids);
    $payload = [];
    foreach ($reversed as $i => $id) {
        $payload[] = ['id' => $id, 'sort_order' => $i];
    }

    $this->actingAs($this->superAdmin)
        ->putJson(dashboardUrl($this->config->id, 'widgets/reorder'), ['items' => $payload])
        ->assertOk();

    $after = $this->config->pageWidgets()->orderBy('sort_order')->pluck('id')->all();
    expect($after)->toBe($reversed);
});

it('deletes a widget belonging to the config', function () {
    $widget = $this->config->pageWidgets()->first();

    $this->actingAs($this->superAdmin)
        ->deleteJson(dashboardUrl($this->config->id, "widgets/{$widget->id}"))
        ->assertOk();

    expect(PageWidget::find($widget->id))->toBeNull();
});

// ── widget-types filter ────────────────────────────────────────────────────

it('widget-types endpoint returns only widgets whose allowedSlots includes dashboard_grid', function () {
    $response = $this->actingAs($this->superAdmin)
        ->getJson(dashboardUrl($this->config->id, 'widget-types'))
        ->assertOk();

    $handles = array_column($response->json('widget_types'), 'handle');

    expect($handles)->toEqualCanonicalizing(['memos', 'quick_actions', 'this_weeks_events', 'random_data_generator', 'setup_checklist']);
});
