<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function rslvr(): WidgetConfigResolver
{
    return app(WidgetConfigResolver::class);
}

function navPageWidget(array $config = []): PageWidget
{
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'nav')->firstOrFail();
    $page = Page::factory()->create(['title' => 'R', 'slug' => 'r-' . uniqid(), 'status' => 'published']);

    return PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

function nonDefinitionPageWidget(array $schema, array $config = []): PageWidget
{
    $wt = WidgetType::create([
        'handle'        => 'plain_' . uniqid(),
        'label'         => 'Plain',
        'render_mode'   => 'server',
        'collections'   => [],
        'config_schema' => $schema,
        'template'      => '',
    ]);
    $page = Page::factory()->create(['title' => 'P', 'slug' => 'p-' . uniqid(), 'status' => 'published']);

    return PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'config'         => $config,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
}

it('returns definition defaults when instance config is empty (registry widget)', function () {
    $pw = navPageWidget([]);
    $resolved = rslvr()->resolve($pw);

    expect($resolved['link_color'])->toBe('#1d4ed8');
    expect($resolved['branding_type'])->toBe('none');
    expect($resolved['drop_fill_color'])->toBe('#ffffff');
});

it('falls back to WidgetType::getDefaultConfig for widgets without a definition', function () {
    $pw = nonDefinitionPageWidget([
        ['key' => 'heading', 'type' => 'text', 'default' => 'Hello'],
        ['key' => 'count',   'type' => 'number', 'default' => 3],
    ]);

    $resolved = rslvr()->resolve($pw);

    expect($resolved['heading'])->toBe('Hello');
    expect($resolved['count'])->toBe(3);
});

it('instance config values override defaults key by key', function () {
    $pw = navPageWidget(['link_color' => '#ff0000']);
    $resolved = rslvr()->resolve($pw);

    expect($resolved['link_color'])->toBe('#ff0000');
    expect($resolved['hover_color'])->toBe('#60a5fa');
});

it('hasOverride distinguishes overridden vs inherited keys', function () {
    $pw = navPageWidget(['link_color' => '#ff0000', 'hover_color' => '#60a5fa']);

    expect(rslvr()->hasOverride($pw, 'link_color'))->toBeTrue();
    expect(rslvr()->hasOverride($pw, 'hover_color'))->toBeFalse();
    expect(rslvr()->hasOverride($pw, 'branding_type'))->toBeFalse();
});

it('resolvedDefaults returns the defaults map without the instance layer', function () {
    $pw = navPageWidget(['link_color' => '#ff0000']);
    $defaults = rslvr()->resolvedDefaults($pw);

    expect($defaults['link_color'])->toBe('#1d4ed8');
});

it('defaultFor returns the value the resolver would use if the instance did not override', function () {
    $pw = navPageWidget(['link_color' => '#ff0000']);
    expect(rslvr()->defaultFor($pw, 'link_color'))->toBe('#1d4ed8');
    expect(rslvr()->defaultFor($pw, 'missing_key'))->toBeNull();
});

it('pre-existing fat rows still render correctly via the resolver (regression)', function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    $wt = WidgetType::where('handle', 'nav')->firstOrFail();
    $page = Page::factory()->create(['title' => 'F', 'slug' => 'f-' . uniqid(), 'status' => 'published']);

    $fat = $wt->getDefaultConfig();
    $fat['link_color'] = '#123456';

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'config'         => $fat,
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $resolved = rslvr()->resolve($pw);
    expect($resolved['link_color'])->toBe('#123456');
    expect($resolved['hover_color'])->toBe('#60a5fa');
});
