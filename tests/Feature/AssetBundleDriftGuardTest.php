<?php

// Regression guard for the session-296 stale-stylesheet incident: the public
// site served a compiled CSS bundle whose styling no longer matched saved
// settings, and nothing detected the drift. AssetBuildService::bundleDrift()
// reconciles the served build-server bundle (collectSources() → manifest.json)
// against the current source hash.
//
// SCOPE BY INTENT: this guard — and these tests — cover only the build-server
// bundle path. Per-template scheme overrides that the incoming theme/template
// re-taxonomy delivers request-time inline are composed at render time and are
// deliberately drift-proof; they are out of scope by design and intentionally
// not exercised here.
//
// Every test builds into an isolated temp output dir via the injectable
// AssetBuildService constructor and asserts the real public/build tree is
// never written or read — the precise pollution the incident's tests caused.

use App\Models\SiteSetting;
use App\Services\AssetBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tmpOutput = sys_get_temp_dir() . '/np-drift-' . uniqid('', true) . '/widgets';
    $this->tmpLibs   = sys_get_temp_dir() . '/np-drift-' . uniqid('', true) . '/libs';

    $realManifest = public_path('build/widgets/manifest.json');
    $this->realManifestBefore = is_readable($realManifest)
        ? file_get_contents($realManifest)
        : null;

    config([
        'services.build_server.url'     => 'http://fake-build-server:8080',
        'services.build_server.api_key' => 'test-key',
    ]);

    Http::fake([
        '*/build' => Http::response([
            'success' => true,
            'files'   => [
                'css' => ['content' => base64_encode('.np-site{color:#123456}'), 'size_bytes' => 24],
                'js'  => ['content' => base64_encode('// bundle'), 'size_bytes' => 9],
            ],
        ]),
    ]);
});

afterEach(function () {
    // Unconditional cleanup — runs even when an assertion above threw, so a
    // failing test can never leave temp bundles behind (the discipline the
    // incident's tests violated).
    foreach ([$this->tmpOutput, $this->tmpLibs] as $dir) {
        $parent = dirname($dir);
        if (File::isDirectory($parent)) {
            File::deleteDirectory($parent);
        }
    }

    // The real served tree must be byte-identical to before this test.
    $realManifest = public_path('build/widgets/manifest.json');
    $after = is_readable($realManifest) ? file_get_contents($realManifest) : null;
    expect($after)->toBe($this->realManifestBefore);
});

it('reports fresh immediately after a build into an isolated output dir', function () {
    $service = new AssetBuildService($this->tmpOutput, $this->tmpLibs);

    $result = $service->build();

    expect($result->success)->toBeTrue();
    expect(File::exists($this->tmpOutput . '/manifest.json'))->toBeTrue();
    // Nothing in the real served dir was touched (asserted hard in afterEach).
    expect($service->bundleDrift())->toBeNull();
});

it('detects drift when a design-group setting changes with no rebuild', function () {
    $service = new AssetBuildService($this->tmpOutput, $this->tmpLibs);

    expect($service->build()->success)->toBeTrue();
    expect($service->bundleDrift())->toBeNull(); // fresh baseline

    // Mutate a setting in the design group AssetBuildService folds into
    // collectSources() (button_styles → generateButtonOverrideCss). No rebuild.
    SiteSetting::create([
        'key'   => 'button_styles',
        'value' => json_encode(['primary' => ['bg_color' => '#abcdef']]),
        'type'  => 'json',
        'group' => AssetBuildService::DESIGN_SETTINGS_GROUP,
    ]);
    Cache::forget('site_setting:button_styles');

    $reason = $service->bundleDrift();

    expect($reason)->not->toBeNull();
    expect($reason)->toContain('no longer matches current source hash');
});

it('detects drift when no bundle has been built while sources exist', function () {
    // Temp output dir is empty — no manifest. Source content (the SCSS
    // partials) exists, so a missing manifest is itself drift.
    $service = new AssetBuildService($this->tmpOutput, $this->tmpLibs);

    $reason = $service->bundleDrift();

    expect($reason)->not->toBeNull();
    expect($reason)->toContain('No served manifest');
});

it('detects drift when the manifest declares no CSS bundle', function () {
    File::ensureDirectoryExists($this->tmpOutput);
    File::put(
        $this->tmpOutput . '/manifest.json',
        json_encode(['css' => null, 'js' => null, 'libs' => [], 'built_at' => now()->toIso8601String()]),
    );

    $service = new AssetBuildService($this->tmpOutput, $this->tmpLibs);

    expect($service->bundleDrift())->toContain('declares no CSS bundle');
});
