<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

function defaultsUser(array $permissions = ['view_page', 'update_page']): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

function defaultsApiPrefix(): string
{
    return '/' . config('filament.path', env('ADMIN_PATH', 'admin')) . '/api/page-builder';
}

function defaultsHeroWidget(array $config = []): PageWidget
{
    $page = Page::factory()->create([
        'title'  => 'Defaults Test Page',
        'slug'   => 'defaults-test-' . uniqid(),
        'status' => 'published',
    ]);

    $wt = WidgetType::where('handle', 'hero')->firstOrFail();

    return PageWidget::create([
        'page_id'           => $page->id,
        'widget_type_id'    => $wt->id,
        'label'             => 'Hero',
        'config'            => $config,
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

it('exports a widget defaults() method with every schema key in declared order', function () {
    $widget = defaultsHeroWidget(['text_position' => 'top-left', 'fullscreen' => true]);

    $response = $this->actingAs(defaultsUser())
        ->postJson(defaultsApiPrefix() . '/widget-defaults/export', [
            'widget_id' => $widget->id,
        ]);

    $response->assertOk();
    $php = $response->json('php');

    expect($php)->toContain('public function defaults(): array');
    expect($php)->toContain("'text_position'");
    expect($php)->toContain("'top-left'");
    expect($php)->toContain("'fullscreen'");
    expect($php)->toContain('true');
    expect($php)->toContain("'min_height'");
    expect($php)->toContain("'24rem'");

    // Schema declaration order: text_position precedes fullscreen precedes min_height.
    expect(strpos($php, "'text_position'"))->toBeLessThan(strpos($php, "'fullscreen'"));
    expect(strpos($php, "'fullscreen'"))->toBeLessThan(strpos($php, "'min_height'"));
});

it('round-trips: emitted PHP parses back to the resolved config', function () {
    $widget = defaultsHeroWidget(['text_position' => 'center-left', 'fullscreen' => true]);

    $php = $this->actingAs(defaultsUser())
        ->postJson(defaultsApiPrefix() . '/widget-defaults/export', [
            'widget_id' => $widget->id,
        ])
        ->json('php');

    $class = 'WidgetDefaultsExport_' . uniqid();
    eval("class {$class} { {$php} }");
    $roundTripped = (new $class())->defaults();

    expect($roundTripped)->toBeArray();
    expect($roundTripped['text_position'])->toBe('center-left');
    expect($roundTripped['fullscreen'])->toBeTrue();
    expect($roundTripped['min_height'])->toBe('24rem');
    expect($roundTripped['background_overlay_opacity'])->toBe(50);
});

it('rejects unauthenticated defaults export', function () {
    $widget = defaultsHeroWidget();

    $this->postJson(defaultsApiPrefix() . '/widget-defaults/export', [
        'widget_id' => $widget->id,
    ])->assertStatus(401);
});

it('export emits a defaultAppearanceConfig() method with the widget appearance merged onto concrete defaults', function () {
    $widget = defaultsHeroWidget();
    $widget->update([
        'appearance_config' => [
            'layout' => [
                'padding' => ['top' => 50, 'right' => 50, 'bottom' => 50, 'left' => 50],
            ],
        ],
    ]);

    $php = $this->actingAs(defaultsUser())
        ->postJson(defaultsApiPrefix() . '/widget-defaults/export', [
            'widget_id' => $widget->id,
        ])
        ->json('php');

    expect($php)->toContain('public function defaultAppearanceConfig(): array');

    $class = 'WidgetAppearanceExport_' . uniqid();
    eval("class {$class} { {$php} }");
    $appearance = (new $class())->defaultAppearanceConfig();

    expect($appearance)->toBeArray();
    expect($appearance['layout']['padding'])->toBe([
        'top' => 50, 'right' => 50, 'bottom' => 50, 'left' => 50,
    ]);
    // Unset sides fall through to the concrete zero-state default, never null/missing.
    expect($appearance['layout']['margin'])->toBe([
        'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0,
    ]);
    expect($appearance['layout']['full_width'])->toBeFalse();
    expect($appearance['background']['color'])->toBe('#ffffff');
    expect($appearance['text']['color'])->toBe('#000000');
});

it('export skips null/empty overrides and coerces numeric strings to int so unset sides stay concrete', function () {
    $widget = defaultsHeroWidget();
    $widget->update([
        'appearance_config' => [
            'layout' => [
                'padding' => [
                    'top'    => '150',
                    'right'  => null,
                    'bottom' => '150',
                    'left'   => '',
                ],
            ],
        ],
    ]);

    $php = $this->actingAs(defaultsUser())
        ->postJson(defaultsApiPrefix() . '/widget-defaults/export', [
            'widget_id' => $widget->id,
        ])
        ->json('php');

    $class = 'WidgetAppearanceMerge_' . uniqid();
    eval("class {$class} { {$php} }");
    $appearance = (new $class())->defaultAppearanceConfig();

    expect($appearance['layout']['padding'])->toBe([
        'top' => 150, 'right' => 0, 'bottom' => 150, 'left' => 0,
    ]);
});

it('rejects defaults export from users without update_page permission', function () {
    $widget = defaultsHeroWidget();
    $user = defaultsUser(['view_page']);

    $this->actingAs($user)
        ->postJson(defaultsApiPrefix() . '/widget-defaults/export', [
            'widget_id' => $widget->id,
        ])->assertStatus(403);
});
