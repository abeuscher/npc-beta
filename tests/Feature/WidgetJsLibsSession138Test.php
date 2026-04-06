<?php

use App\Livewire\PageBuilderBlock;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\AssetBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\PermissionSeeder())->run();
    (new \Database\Seeders\WidgetTypeSeeder())->run();
});

// ── Phase 1: libs declarations ──────────────────────────────────────

it('carousel widget type has swiper in libs', function () {
    $wt = WidgetType::where('handle', 'carousel')->first();
    expect($wt->assets['libs'] ?? [])->toContain('swiper');
});

it('product_carousel widget type has swiper in libs', function () {
    $wt = WidgetType::where('handle', 'product_carousel')->first();
    expect($wt->assets['libs'] ?? [])->toContain('swiper');
});

it('logo_garden widget type has swiper in libs', function () {
    $wt = WidgetType::where('handle', 'logo_garden')->first();
    expect($wt->assets['libs'] ?? [])->toContain('swiper');
});

it('events_listing widget type has swiper in libs', function () {
    $wt = WidgetType::where('handle', 'events_listing')->first();
    expect($wt->assets['libs'] ?? [])->toContain('swiper');
});

it('blog_listing widget type has swiper in libs', function () {
    $wt = WidgetType::where('handle', 'blog_listing')->first();
    expect($wt->assets['libs'] ?? [])->toContain('swiper');
});

it('bar_chart widget type has chart.js in libs', function () {
    $wt = WidgetType::where('handle', 'bar_chart')->first();
    expect($wt->assets['libs'] ?? [])->toContain('chart.js');
});

it('event_calendar widget type has jcalendar in libs', function () {
    $wt = WidgetType::where('handle', 'event_calendar')->first();
    expect($wt->assets['libs'] ?? [])->toContain('jcalendar');
});

it('text_block widget type has no libs', function () {
    $wt = WidgetType::where('handle', 'text_block')->first();
    expect($wt->assets['libs'] ?? [])->toBeEmpty();
});

// ── Phase 2: library bundle build ───────────────────────────────────

it('buildLibraryBundles produces files and returns paths', function () {
    $service = app(AssetBuildService::class);
    $method = new ReflectionMethod($service, 'buildLibraryBundles');

    // Fake HTTP — simulate build server returning a JS bundle for each lib
    Http::fake([
        '*/build' => Http::response([
            'success' => true,
            'files' => [
                'css' => ['content' => '', 'size_bytes' => 0],
                'js' => ['content' => base64_encode('/* compiled lib */'), 'size_bytes' => 20],
            ],
        ]),
    ]);

    $libs = $method->invoke($service, 'http://fake-build-server:8080', 'test-key', false);

    expect($libs)->toHaveKeys(['swiper', 'chart.js', 'jcalendar']);
    expect($libs['swiper']['js'])->toBe('/build/libs/swiper.js');
    expect($libs['chart.js']['js'])->toBe('/build/libs/chartjs.js');
    expect($libs['jcalendar']['js'])->toBe('/build/libs/jcalendar.js');

    // Files should exist on disk
    expect(File::exists(public_path('build/libs/swiper.js')))->toBeTrue();
    expect(File::exists(public_path('build/libs/chartjs.js')))->toBeTrue();
    expect(File::exists(public_path('build/libs/jcalendar.js')))->toBeTrue();

    // Clean up
    File::deleteDirectory(public_path('build/libs'));
});

it('manifest includes libs key after a successful build', function () {
    Http::fake([
        '*/build' => Http::response([
            'success' => true,
            'files' => [
                'css' => ['content' => base64_encode('body{}'), 'size_bytes' => 6],
                'js' => ['content' => base64_encode('// js'), 'size_bytes' => 5],
            ],
        ]),
    ]);

    config([
        'services.build_server.url' => 'http://fake-build-server:8080',
        'services.build_server.api_key' => 'test-key',
    ]);

    $service = app(AssetBuildService::class);
    $result = $service->build();

    expect($result->success)->toBeTrue();

    $manifest = json_decode(File::get(public_path('build/widgets/manifest.json')), true);
    expect($manifest)->toHaveKey('libs');
    expect($manifest['libs'])->toHaveKeys(['swiper', 'chart.js', 'jcalendar']);

    // Clean up
    File::deleteDirectory(public_path('build/libs'));
    File::deleteDirectory(public_path('build/widgets'));
});

// ── Phase 3: block data includes widget_type_assets ─────────────────

it('loadBlock includes widget_type_assets in block array', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $wt = WidgetType::where('handle', 'carousel')->firstOrFail();
    $page = Page::factory()->create(['title' => 'Test', 'slug' => 'test-' . uniqid(), 'status' => 'published']);

    $pw = PageWidget::create([
        'page_id'        => $page->id,
        'widget_type_id' => $wt->id,
        'label'          => 'Test Carousel',
        'config'         => $wt->getDefaultConfig(),
        'query_config'   => [],
        'style_config'   => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);

    $component = Livewire::test(PageBuilderBlock::class, [
        'blockId' => $pw->id,
    ]);

    $block = $component->get('block');
    expect($block)->toHaveKey('widget_type_assets');
    expect($block['widget_type_assets']['libs'] ?? [])->toContain('swiper');
});
