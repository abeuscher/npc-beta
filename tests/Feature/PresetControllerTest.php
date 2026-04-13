<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetPreset;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function presetUser(array $permissions = ['view_page', 'update_page']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function presetApiPrefix(): string
{
    return '/' . config('filament.path', env('ADMIN_PATH', 'admin')) . '/api/page-builder';
}

function presetHeroWidget(): PageWidget
{
    $page = Page::factory()->create([
        'title'  => 'Preset Test Page',
        'slug'   => 'preset-test-' . uniqid(),
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', 'hero')->firstOrFail();

    return PageWidget::create([
        'page_id'           => $page->id,
        'widget_type_id'    => $wt->id,
        'label'             => 'Hero',
        'config'            => [
            'content'       => '<p>Hello</p>',
            'text_position' => 'center-center',
            'fullscreen'    => true,
        ],
        'query_config'      => [],
        'appearance_config' => [
            'background' => ['color' => '#111111'],
            'text'       => ['color' => '#ffffff'],
        ],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

// ── POST /widget-presets ─────────────────────────────────────────────────

it('saves a draft preset capturing only appearance-group config keys', function () {
    $widget = presetHeroWidget();

    $response = $this->actingAs(presetUser())
        ->postJson(presetApiPrefix() . '/widget-presets', [
            'widget_type_id' => $widget->widget_type_id,
            'widget_id'      => $widget->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('preset.handle', 'draft-1')
        ->assertJsonPath('preset.label', 'Draft 1')
        ->assertJsonPath('preset.is_draft', true);

    $preset = WidgetPreset::first();
    expect($preset)->not->toBeNull();

    // Appearance-group keys are captured; content-group keys are stripped.
    expect($preset->config)->toHaveKeys(['text_position', 'fullscreen']);
    expect($preset->config)->not->toHaveKey('content');
    expect($preset->appearance_config)->toEqual([
        'background' => ['color' => '#111111'],
        'text'       => ['color' => '#ffffff'],
    ]);
});

it('materializes defaults for appearance-group keys not set on the instance', function () {
    // The instance only sets one appearance key. Every other appearance-group key
    // should be captured at its resolved default so apply produces a complete look.
    $page = Page::factory()->create([
        'title'  => 'Preset Defaults Page',
        'slug'   => 'preset-defaults-' . uniqid(),
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', 'hero')->firstOrFail();

    $widget = PageWidget::create([
        'page_id'           => $page->id,
        'widget_type_id'    => $wt->id,
        'label'             => 'Hero',
        'config'            => ['text_position' => 'top-left'],
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);

    $this->actingAs(presetUser())
        ->postJson(presetApiPrefix() . '/widget-presets', [
            'widget_type_id' => $widget->widget_type_id,
            'widget_id'      => $widget->id,
        ])->assertCreated();

    $preset = WidgetPreset::first();

    // Instance-set value is preserved.
    expect($preset->config['text_position'])->toBe('top-left');

    // Defaults for every other appearance-group key are materialized (e.g. min_height default '24rem').
    expect($preset->config)->toHaveKey('min_height');
    expect($preset->config['min_height'])->toBe('24rem');
    expect($preset->config)->toHaveKey('fullscreen');
    expect($preset->config['fullscreen'])->toBeFalse();
});

it('auto-increments draft handles per widget type', function () {
    $widget = presetHeroWidget();

    $this->actingAs(presetUser())
        ->postJson(presetApiPrefix() . '/widget-presets', [
            'widget_type_id' => $widget->widget_type_id,
            'widget_id'      => $widget->id,
        ])->assertCreated();

    $this->actingAs(presetUser())
        ->postJson(presetApiPrefix() . '/widget-presets', [
            'widget_type_id' => $widget->widget_type_id,
            'widget_id'      => $widget->id,
        ])
        ->assertCreated()
        ->assertJsonPath('preset.handle', 'draft-2')
        ->assertJsonPath('preset.label', 'Draft 2');
});

it('rejects unauthenticated save requests', function () {
    $widget = presetHeroWidget();

    $this->postJson(presetApiPrefix() . '/widget-presets', [
        'widget_type_id' => $widget->widget_type_id,
        'widget_id'      => $widget->id,
    ])->assertStatus(401);
});

it('rejects save from users without update_page permission', function () {
    $widget = presetHeroWidget();
    $user = presetUser(['view_page']);

    $this->actingAs($user)
        ->postJson(presetApiPrefix() . '/widget-presets', [
            'widget_type_id' => $widget->widget_type_id,
            'widget_id'      => $widget->id,
        ])->assertStatus(403);
});

// ── PATCH /widget-presets/{preset} ───────────────────────────────────────

it('renames a draft preset and enforces handle uniqueness', function () {
    $widget = presetHeroWidget();
    $wtId = $widget->widget_type_id;

    $existing = WidgetPreset::create([
        'widget_type_id' => $wtId,
        'handle'         => 'dark-hero',
        'label'          => 'Dark Hero',
        'config'         => [],
        'appearance_config' => [],
    ]);

    $target = WidgetPreset::create([
        'widget_type_id' => $wtId,
        'handle'         => 'draft-1',
        'label'          => 'Draft 1',
        'config'         => [],
        'appearance_config' => [],
    ]);

    $this->actingAs(presetUser())
        ->patchJson(presetApiPrefix() . '/widget-presets/' . $target->id, [
            'label'       => 'Renamed',
            'description' => 'A description.',
            'handle'      => 'renamed-draft',
        ])
        ->assertOk()
        ->assertJsonPath('preset.label', 'Renamed')
        ->assertJsonPath('preset.handle', 'renamed-draft')
        ->assertJsonPath('preset.description', 'A description.');

    // Collision with existing handle on the same widget type is rejected.
    $this->actingAs(presetUser())
        ->patchJson(presetApiPrefix() . '/widget-presets/' . $target->id, [
            'handle' => 'dark-hero',
        ])
        ->assertStatus(422);
});

// ── DELETE /widget-presets/{preset} ──────────────────────────────────────

it('deletes a draft preset', function () {
    $widget = presetHeroWidget();

    $preset = WidgetPreset::create([
        'widget_type_id' => $widget->widget_type_id,
        'handle'         => 'draft-1',
        'label'          => 'Draft 1',
        'config'         => [],
        'appearance_config' => [],
    ]);

    $this->actingAs(presetUser())
        ->deleteJson(presetApiPrefix() . '/widget-presets/' . $preset->id)
        ->assertOk()
        ->assertJson(['deleted' => true]);

    expect(WidgetPreset::find($preset->id))->toBeNull();
});

// ── Preset shape validity (mirror of WidgetManifestTest for DB rows) ─────

it('DB draft presets reference only appearance-group schema keys on their widget type', function () {
    $registry = app(\App\Services\WidgetRegistry::class);
    $widgetType = WidgetType::where('handle', 'hero')->firstOrFail();

    WidgetPreset::create([
        'widget_type_id'    => $widgetType->id,
        'handle'            => 'draft-shape-check',
        'label'             => 'Draft Shape Check',
        'config'            => ['text_position' => 'top-left', 'fullscreen' => true],
        'appearance_config' => ['background' => ['color' => '#000']],
    ]);

    foreach (WidgetPreset::with('widgetType')->get() as $row) {
        $def = $registry->find($row->widgetType->handle);
        $appearanceKeys = collect($def?->schema() ?? [])
            ->filter(fn ($field) => ($field['group'] ?? 'content') === 'appearance')
            ->pluck('key')
            ->filter()
            ->all();

        expect((bool) preg_match('/^[a-z0-9-]+$/', $row->handle))->toBeTrue();
        expect(is_string($row->label) && $row->label !== '')->toBeTrue();
        expect(is_array($row->config))->toBeTrue();
        expect(is_array($row->appearance_config))->toBeTrue();

        foreach (array_keys($row->config) as $key) {
            expect(in_array($key, $appearanceKeys, true))->toBeTrue(
                "DB preset [{$row->handle}] references non-appearance key: {$key}"
            );
        }
    }
});

// ── Bootstrap payload carries draft_presets alongside code presets ───────

it('exposes DB draft presets through the page-builder bootstrap payload', function () {
    $widget = presetHeroWidget();

    WidgetPreset::create([
        'widget_type_id' => $widget->widget_type_id,
        'handle'         => 'draft-1',
        'label'          => 'Draft 1',
        'config'         => ['text_position' => 'top-left'],
        'appearance_config' => ['background' => ['color' => '#222222']],
    ]);

    $bootstrap = \Livewire\Livewire::test(\App\Livewire\PageBuilder::class, ['pageId' => $widget->page_id])
        ->instance()
        ->getBootstrapData();

    $hero = collect($bootstrap['widget_types'])->firstWhere('handle', 'hero');

    expect($hero)->toHaveKey('presets')
        ->and($hero)->toHaveKey('draft_presets')
        ->and($hero['draft_presets'])->toBeArray()->toHaveCount(1);

    $draft = $hero['draft_presets'][0];
    expect($draft)->toHaveKeys(['id', 'handle', 'label', 'description', 'config', 'appearance_config', 'is_draft']);
    expect($draft['is_draft'])->toBeTrue();
    expect($draft['handle'])->toBe('draft-1');
});
