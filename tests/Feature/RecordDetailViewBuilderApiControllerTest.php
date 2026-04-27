<?php

use App\Models\Contact;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\WidgetPrimitive\Views\RecordDetailView;
use Database\Seeders\RecordDetailViewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
    (new RecordDetailViewSeeder())->run();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');

    $this->cmsEditor = User::factory()->create();
    $this->cmsEditor->assignRole('cms_editor');

    $this->view = RecordDetailView::query()
        ->where('record_type', Contact::class)
        ->where('handle', 'contact_overview')
        ->firstOrFail();
});

function recordDetailUrl(string $viewId, string $path = ''): string
{
    $base = '/admin/api/record-detail-view-builder/views/' . $viewId;
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

// ── Authorization ───────────────────────────────────────────────────────────

it('returns 403 on index for a user without manage_record_detail_views', function () {
    $this->actingAs($this->cmsEditor)
        ->getJson(recordDetailUrl($this->view->id, 'widgets'))
        ->assertStatus(403);
});

it('returns 200 on index for a super_admin (gate bypass)', function () {
    $this->actingAs($this->superAdmin)
        ->getJson(recordDetailUrl($this->view->id, 'widgets'))
        ->assertOk()
        ->assertJsonStructure(['items', 'required_libs']);
});

it('rejects widget creation from a user without the permission', function () {
    $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();

    $this->actingAs($this->cmsEditor)
        ->postJson(recordDetailUrl($this->view->id, 'widgets'), [
            'widget_type_id' => $placeholder->id,
        ])
        ->assertStatus(403);
});

// ── allowedSlots enforcement ────────────────────────────────────────────────

it('rejects widget creation when the widget handle does not allow record_detail_sidebar', function () {
    $notAllowed = WidgetType::where('handle', 'text_block')->first();

    $response = $this->actingAs($this->superAdmin)
        ->postJson(recordDetailUrl($this->view->id, 'widgets'), [
            'widget_type_id' => $notAllowed->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['error' => "Widget [{$notAllowed->handle}] is not allowed in the record detail sidebar slot."]);

    expect(PageWidget::where('widget_type_id', $notAllowed->id)->count())->toBe(0);
});

it('accepts widget creation for handles whose allowedSlots() includes record_detail_sidebar', function () {
    $this->view->pageWidgets()->delete();

    $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();

    $response = $this->actingAs($this->superAdmin)
        ->postJson(recordDetailUrl($this->view->id, 'widgets'), [
            'widget_type_id' => $placeholder->id,
        ]);

    $response->assertCreated();
    expect($this->view->pageWidgets()->count())->toBe(1);
});

// ── IDOR guard ──────────────────────────────────────────────────────────────

it('returns 404 when updating a widget that belongs to a different View', function () {
    $otherView = RecordDetailView::create([
        'record_type' => Contact::class,
        'handle'      => 'contact_other',
        'label'       => 'Other',
        'sort_order'  => 5,
    ]);

    $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();
    $otherWidget = PageWidget::create([
        'owner_type'        => $otherView->getMorphClass(),
        'owner_id'          => $otherView->getKey(),
        'widget_type_id'    => $placeholder->id,
        'label'             => 'Other',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->putJson(recordDetailUrl($this->view->id, "widgets/{$otherWidget->id}"), [
            'label' => 'Hijacked',
        ])
        ->assertStatus(404);

    expect($otherWidget->fresh()->label)->toBe('Other');
});

it('returns 404 when the widget belongs to a Page (not a RecordDetailView) via the record-detail route', function () {
    $page = \App\Models\Page::factory()->create();
    $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();
    $pageWidget = PageWidget::create([
        'owner_type'        => $page->getMorphClass(),
        'owner_id'          => $page->getKey(),
        'widget_type_id'    => $placeholder->id,
        'label'             => 'On a page',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->deleteJson(recordDetailUrl($this->view->id, "widgets/{$pageWidget->id}"))
        ->assertStatus(404);

    expect(PageWidget::find($pageWidget->id))->not->toBeNull();
});

// ── Appearance whitelist ───────────────────────────────────────────────────

it('strips appearance_config keys outside background/text on update', function () {
    $widget = $this->view->pageWidgets()->first();

    $this->actingAs($this->superAdmin)
        ->putJson(recordDetailUrl($this->view->id, "widgets/{$widget->id}"), [
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

it('reorders widgets within a View', function () {
    $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();

    PageWidget::create([
        'owner_type'        => $this->view->getMorphClass(),
        'owner_id'          => $this->view->getKey(),
        'widget_type_id'    => $placeholder->id,
        'label'             => 'Second',
        'config'            => [],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 1,
        'is_active'         => true,
    ]);

    $ids = $this->view->pageWidgets()->orderBy('sort_order')->pluck('id')->all();

    $reversed = array_reverse($ids);
    $payload = [];
    foreach ($reversed as $i => $id) {
        $payload[] = ['id' => $id, 'type' => 'widget', 'sort_order' => $i];
    }

    $this->actingAs($this->superAdmin)
        ->putJson(recordDetailUrl($this->view->id, 'widgets/reorder'), ['items' => $payload])
        ->assertOk();

    $after = $this->view->pageWidgets()->orderBy('sort_order')->pluck('id')->all();
    expect($after)->toBe($reversed);
});

it('deletes a widget belonging to the View', function () {
    $widget = $this->view->pageWidgets()->first();

    $this->actingAs($this->superAdmin)
        ->deleteJson(recordDetailUrl($this->view->id, "widgets/{$widget->id}"))
        ->assertOk();

    expect(PageWidget::find($widget->id))->toBeNull();
});

// ── widget-types filter ────────────────────────────────────────────────────

it('widget-types endpoint returns only widgets whose allowedSlots includes record_detail_sidebar', function () {
    $response = $this->actingAs($this->superAdmin)
        ->getJson(recordDetailUrl($this->view->id, 'widget-types'))
        ->assertOk();

    $handles = array_column($response->json('widget_types'), 'handle');

    expect($handles)->toEqualCanonicalizing(['record_detail_placeholder', 'recent_notes']);
});
