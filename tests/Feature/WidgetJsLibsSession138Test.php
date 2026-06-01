<?php

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\User;
use App\Models\WidgetType;
use App\Services\AssetBuildService;
use App\Services\WidgetAssetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('design');

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

it('text_block widget type has no libs', function () {
    $wt = WidgetType::where('handle', 'text_block')->first();
    expect($wt->assets['libs'] ?? [])->toBeEmpty();
});

// ── Phase 2: library bundle build ───────────────────────────────────

it('buildLibraryBundles produces files and returns paths', function () {
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

    // Isolated output dir — no test may write or delete the real public/build.
    $tmp = sys_get_temp_dir() . '/np-libs-' . uniqid('', true);

    try {
        $service = new AssetBuildService($tmp . '/widgets', $tmp . '/libs');
        $method  = new ReflectionMethod($service, 'buildLibraryBundles');

        $libs = $method->invoke($service, 'http://fake-build-server:8080', 'test-key', false);

        expect($libs)->toHaveKeys(['swiper', 'chart.js']);
        expect($libs['swiper']['js'])->toBe('/build/libs/swiper.js');
        expect($libs['chart.js']['js'])->toBe('/build/libs/chartjs.js');

        // Files exist in the isolated dir, not the real served tree.
        expect(File::exists($tmp . '/libs/swiper.js'))->toBeTrue();
        expect(File::exists($tmp . '/libs/chartjs.js'))->toBeTrue();
    } finally {
        File::deleteDirectory($tmp);
    }
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

    // Isolated output dir — no test may write or delete the real public/build.
    $tmp = sys_get_temp_dir() . '/np-libs-' . uniqid('', true);

    try {
        $result = (new AssetBuildService($tmp . '/widgets', $tmp . '/libs'))->build();

        expect($result->success)->toBeTrue();

        $manifest = json_decode(File::get($tmp . '/widgets/manifest.json'), true);
        expect($manifest)->toHaveKey('libs');
        expect($manifest['libs'])->toHaveKeys(['swiper', 'chart.js']);
    } finally {
        File::deleteDirectory($tmp);
    }
});

// ── Phase 3: editor↔public vendor-CSS layer parity (session 333) ─────────────
//
// The page-builder canvas renders public widget HTML inside the Filament admin
// document, so widget styles (@layer widgets) and the vendor lib CSS share one
// cascade. On the public site `public.scss` compiles the vendor CSS into
// `@layer reset`, so widget overrides win by layer order (session 332). The
// admin head emits the same vendor CSS for the editor — and if it ships as a
// bare <link>, it is UNLAYERED and beats every layer, so the editor rendered
// Swiper's default pager instead of the widget's designed one (a WYSIWYG break
// found at session 333). This guard locks the fix: the admin head must emit
// vendor CSS into `@layer reset` (via `@import … layer(reset)`), never as an
// unlayered stylesheet link.
//
// This is the standing parity guard for the layering bug. A Playwright
// editor-vs-public behaviour/computed-style harness is NOT viable on the
// isolated e2e stack: that stack deliberately does not run the widget build
// pipeline (no build server in CI — see tests/e2e/page-builder/
// full-width-matrix.spec.ts), so the swiper lib + widget CSS bundle the bug
// depends on are never produced there. Asserting the emission contract at the
// render layer is build-pipeline-independent and catches the exact regression.

it('admin head emits vendor lib CSS into @layer reset, never as an unlayered <link>', function () {
    // Bind a resolver with a fixture manifest declaring a swiper lib + CSS, so
    // this runs even when the build pipeline has produced no real manifest
    // (CI does not run build:public before the test suite).
    $manifestPath = sys_get_temp_dir() . '/np-s333-manifest-' . uniqid('', true) . '.json';
    File::put($manifestPath, json_encode([
        'css'  => 'public-widgets-test.css',
        'js'   => 'public-widgets-test.js',
        'libs' => [
            'swiper'   => ['css' => '/build/libs/swiper.css', 'js' => '/build/libs/swiper.js'],
            'chart.js' => ['js' => '/build/libs/chartjs.js'],
        ],
    ]));
    app()->instance(WidgetAssetResolver::class, new WidgetAssetResolver($manifestPath));

    try {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $html = $this->get('/admin')->assertOk()->getContent();

        // The swiper CSS is emitted, layered into @layer reset.
        expect($html)
            ->toContain('<style data-widget-lib="swiper">')
            ->toContain('@import url("/build/libs/swiper.css") layer(reset)')
            // …and never as the unlayered <link> that caused the parity break.
            ->not->toContain('<link rel="stylesheet" href="/build/libs/swiper.css"');

        // The JS lib still ships as a plain synchronous <script> (unchanged;
        // only CSS needs layering).
        expect($html)->toContain('<script src="/build/libs/swiper.js" data-widget-lib="swiper">');
    } finally {
        File::delete($manifestPath);
    }
});

