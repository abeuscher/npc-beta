<?php

// Session A002 / Phase 2 — close-gate regression.
//
// The public layout used to dump per-instance widget CSS/JS into two
// catch-all <style>/<script> blocks via PageBlockRenderer / ChromeRenderer
// accumulators, on top of the build-server bundle that already shipped
// the same content via public/build/widgets/manifest.json. Operator-
// customised widget types (Filament's css/js textareas on WidgetType)
// got double-emitted: once in the bundle, once inline per widget
// instance per page render. Phase 2 retired the inline path; this test
// guards that retirement.
//
// Shape: seed two widget types with non-empty css/js, render a page
// that uses them, assert the served HTML carries the bundled <link>
// and <script> exactly once and never carries the widget-type CSS/JS
// string inline. Two assertions — bundle present, inline absent —
// belt + suspenders.
//
// The manifest is written to public/build/widgets/manifest.json at
// test time and rolled back after every case. The hashes do not need
// to resolve to real files for the layout to emit the bundle tags;
// only the manifest's existence + non-empty keys matter.

use App\Models\Page;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->manifestPath = public_path('build/widgets/manifest.json');
    $this->manifestDir  = dirname($this->manifestPath);

    $this->manifestExisted = file_exists($this->manifestPath);
    $this->manifestBefore  = $this->manifestExisted
        ? file_get_contents($this->manifestPath)
        : null;
    $this->manifestDirExisted = File::isDirectory($this->manifestDir);

    if (! $this->manifestDirExisted) {
        File::makeDirectory($this->manifestDir, 0755, true);
    }

    file_put_contents($this->manifestPath, json_encode([
        'css' => 'a002-test.css',
        'js'  => 'a002-test.js',
    ]));
});

afterEach(function () {
    if ($this->manifestExisted) {
        file_put_contents($this->manifestPath, $this->manifestBefore);
    } else {
        if (file_exists($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        if (! $this->manifestDirExisted && File::isDirectory($this->manifestDir)) {
            File::deleteDirectory($this->manifestDir);
        }
    }
});

it('emits exactly one bundled widget <link> + <script> and never inlines the widget-type CSS/JS', function () {
    $cssMarkerOne = '.a002-marker-one{outline:1px solid magenta}';
    $jsMarkerOne  = 'window.__a002MarkerOne = 1;';
    $cssMarkerTwo = '.a002-marker-two{outline:1px solid lime}';
    $jsMarkerTwo  = 'window.__a002MarkerTwo = 2;';

    $typeOne = WidgetType::factory()->create([
        'handle'      => 'a002-type-one',
        'label'       => 'A002 Type One',
        'template'    => '<div class="a002-one">marker-one-html</div>',
        'render_mode' => 'server',
        'css'         => $cssMarkerOne,
        'js'          => $jsMarkerOne,
    ]);

    $typeTwo = WidgetType::factory()->create([
        'handle'      => 'a002-type-two',
        'label'       => 'A002 Type Two',
        'template'    => '<div class="a002-two">marker-two-html</div>',
        'render_mode' => 'server',
        'css'         => $cssMarkerTwo,
        'js'          => $jsMarkerTwo,
    ]);

    $page = Page::factory()->create([
        'slug'         => 'a002-bundle-consolidation',
        'status'       => 'published',
        'published_at' => now(),
    ]);

    $page->widgets()->create([
        'widget_type_id' => $typeOne->id,
        'config'         => [],
        'sort_order'     => 0,
        'is_active'      => true,
    ]);
    $page->widgets()->create([
        'widget_type_id' => $typeTwo->id,
        'config'         => [],
        'sort_order'     => 1,
        'is_active'      => true,
    ]);

    $response = $this->get('/a002-bundle-consolidation');
    $response->assertOk();
    $body = $response->getContent();

    // Bundle present, exactly once.
    expect(preg_match_all('#<link\s[^>]*href="/build/widgets/[^"]+"#', $body))->toBe(1);
    expect(preg_match_all('#<script\s[^>]*src="/build/widgets/[^"]+"#', $body))->toBe(1);

    // Inline absent. The widget-type CSS string must not appear in any
    // <style> block, and the widget-type JS string must not appear in
    // any inline <script> block (the bundled <script src=...> tag is
    // not inline — it only references the hashed filename).
    expect($body)->not->toContain($cssMarkerOne)
        ->and($body)->not->toContain($cssMarkerTwo)
        ->and($body)->not->toContain($jsMarkerOne)
        ->and($body)->not->toContain($jsMarkerTwo);
});
