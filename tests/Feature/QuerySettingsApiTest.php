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

function qsApiUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view_page', 'update_page']);
    return $user;
}

function qsApiPage(): Page
{
    return Page::factory()->create([
        'title'  => 'QS API Page',
        'slug'   => 'qs-api-' . uniqid(),
        'status' => 'published',
    ]);
}

function qsApiWidget(Page $page, string $handle): PageWidget
{
    $wt = WidgetType::where('handle', $handle)->firstOrFail();
    return $page->widgets()->create([
        'widget_type_id'    => $wt->id,
        'label'             => $wt->label,
        'config'            => $wt->getDefaultConfig(),
        'query_config'      => [],
        'appearance_config' => [],
        'sort_order'        => 0,
        'is_active'         => true,
    ]);
}

function qsApiPrefix(): string
{
    return '/' . config('filament.path', env('ADMIN_PATH', 'admin')) . '/api/page-builder';
}

it('returns query_settings.has_panel=true with order_by_options for list-shaped widgets', function () {
    $page = qsApiPage();
    qsApiWidget($page, 'carousel');
    qsApiWidget($page, 'blog_listing');
    qsApiWidget($page, 'events_listing');
    qsApiWidget($page, 'board_members');
    qsApiWidget($page, 'product_carousel');

    $response = $this->actingAs(qsApiUser())
        ->getJson(qsApiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();
    $widgets = collect($response->json('items'))->filter(fn ($w) => ($w['type'] ?? null) === 'widget');

    $byHandle = $widgets->keyBy('widget_type_handle');

    expect($byHandle['carousel']['query_settings']['has_panel'])->toBeTrue()
        ->and($byHandle['carousel']['query_settings']['supports_tags'])->toBeTrue()
        ->and(array_keys($byHandle['carousel']['query_settings']['order_by_options']))
            ->toEqualCanonicalizing(['title', 'description', 'sort_order', 'created_at', 'updated_at']);

    expect($byHandle['blog_listing']['query_settings']['has_panel'])->toBeTrue()
        ->and(array_keys($byHandle['blog_listing']['query_settings']['order_by_options']))
            ->toEqualCanonicalizing(['published_at', 'created_at', 'updated_at', 'title']);

    expect($byHandle['events_listing']['query_settings']['has_panel'])->toBeTrue()
        ->and(array_keys($byHandle['events_listing']['query_settings']['order_by_options']))
            ->toEqualCanonicalizing(['starts_at', 'ends_at', 'created_at', 'title']);

    expect($byHandle['board_members']['query_settings']['has_panel'])->toBeTrue();

    expect($byHandle['product_carousel']['query_settings']['has_panel'])->toBeTrue()
        ->and($byHandle['product_carousel']['query_settings']['supports_tags'])->toBeFalse()
        ->and(array_keys($byHandle['product_carousel']['query_settings']['order_by_options']))
            ->toEqualCanonicalizing(['sort_order', 'name', 'created_at', 'updated_at']);
});

it('returns query_settings=null for non-list widgets (TextBlock)', function () {
    $page = qsApiPage();
    qsApiWidget($page, 'text_block');

    $response = $this->actingAs(qsApiUser())
        ->getJson(qsApiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();
    $widget = collect($response->json('items'))->firstWhere('widget_type_handle', 'text_block');

    expect($widget['query_settings'])->toBeNull();
});

it('does not include widget_type_collections on the per-widget response', function () {
    $page = qsApiPage();
    qsApiWidget($page, 'carousel');

    $response = $this->actingAs(qsApiUser())
        ->getJson(qsApiPrefix() . "/pages/{$page->id}/widgets");

    $response->assertOk();
    $widget = collect($response->json('items'))->firstWhere('widget_type_handle', 'carousel');

    expect($widget)->not->toHaveKey('widget_type_collections');
});

it('round-trips honored knobs and drops unknown query_config keys', function () {
    $page = qsApiPage();
    $widget = qsApiWidget($page, 'carousel');

    $this->actingAs(qsApiUser())
        ->putJson(qsApiPrefix() . "/widgets/{$widget->id}", [
            'query_config' => [
                'limit'         => 4,
                'order_by'      => 'title',
                'direction'     => 'desc',
                'include_tags'  => ['x'],
                'exclude_tags'  => ['y'],
                'unknown_knob'  => 'sentinel',
            ],
        ])->assertOk();

    $widget->refresh();

    expect($widget->query_config)
        ->toHaveKey('limit')
        ->toHaveKey('order_by')
        ->toHaveKey('direction')
        ->toHaveKey('include_tags')
        ->toHaveKey('exclude_tags')
        ->not->toHaveKey('unknown_knob');
});

it('persists flat query_config and strips legacy slot-keyed payloads', function () {
    $page = qsApiPage();
    $widget = qsApiWidget($page, 'carousel');

    // Flat payload — kept verbatim.
    $this->actingAs(qsApiUser())
        ->putJson(qsApiPrefix() . "/widgets/{$widget->id}", [
            'query_config' => ['limit' => 4],
        ])->assertOk();

    $widget->refresh();
    expect($widget->query_config)->toBe(['limit' => 4]);

    // Legacy slot-keyed payload — no recognized top-level knob key, all dropped.
    $this->actingAs(qsApiUser())
        ->putJson(qsApiPrefix() . "/widgets/{$widget->id}", [
            'query_config' => ['slides' => ['limit' => 4]],
        ])->assertOk();

    $widget->refresh();
    expect($widget->query_config)->toBe([]);
});
