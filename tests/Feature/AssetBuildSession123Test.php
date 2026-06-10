<?php

use App\Models\WidgetType;
use App\Services\AssetBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('design');

it('collects scss sources from partials in correct order', function () {
    $service = app(AssetBuildService::class);
    $method = new ReflectionMethod($service, 'collectSources');
    $sources = $method->invoke($service);

    expect($sources)->toHaveKeys(['scss', 'css', 'js']);
    expect($sources['scss'])->not->toBeEmpty();

    // The first SCSS entry should be the combined theme file
    $themeSource = $sources['scss'][0];
    expect($themeSource['path'])->toBe('theme/public.scss');
    expect($themeSource['content'])->toContain('$bp-md');
    expect($themeSource['content'])->toContain('.site-container');
    // @use lines should be stripped
    expect($themeSource['content'])->not->toContain('@use "variables"');
});

it('collects widget css and js from widget type records', function () {
    WidgetType::create([
        'handle' => 'test_widget_' . uniqid(),
        'label' => 'Test Widget',
        'category' => ['content'],
        'css' => '.test-widget { color: red; }',
        'js' => 'console.log("test");',
    ]);

    $service = app(AssetBuildService::class);
    $method = new ReflectionMethod($service, 'collectSources');
    $sources = $method->invoke($service);

    $cssContents = collect($sources['css'])->pluck('content')->implode('');
    $jsContents = collect($sources['js'])->pluck('content')->implode('');

    expect($cssContents)->toContain('.test-widget { color: red; }');
    expect($jsContents)->toContain('console.log("test")');
});

it('build() writes an editor-variant bundle with width media rewritten to container queries', function () {
    $outDir = sys_get_temp_dir() . '/asset-build-' . uniqid('', true);
    $libsDir = $outDir . '/libs';

    config([
        'services.build_server.url' => 'http://bundleserver.test',
        'services.build_server.api_key' => 'test-key',
    ]);

    $compiledCss = '@media (max-width: 768px) { .widget { padding-top: 8px; } } @media print { .a { display: none; } }';
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'success' => true,
            'files' => [
                'css' => ['content' => base64_encode($compiledCss)],
                'js'  => ['content' => base64_encode('console.log(1);')],
            ],
        ]),
    ]);

    $result = (new AssetBuildService($outDir, $libsDir))->build();

    expect($result->success)->toBeTrue();

    $manifest = json_decode(file_get_contents($outDir . '/manifest.json'), true);
    expect($manifest['editor_css'])
        ->toBe(str_replace('public-widgets-', 'public-widgets-editor-', $manifest['css']));

    // The editor variant carries the rewrite; the public bundle is untouched.
    $editor = file_get_contents($outDir . '/' . $manifest['editor_css']);
    expect($editor)->toContain('@container np-viewport (max-width: 768px)');
    expect($editor)->not->toContain('@media (max-width: 768px)');
    expect($editor)->toContain('@media print');

    $public = file_get_contents($outDir . '/' . $manifest['css']);
    expect($public)->toBe($compiledCss);

    \Illuminate\Support\Facades\File::deleteDirectory($outDir);
});

it('build:public command fails gracefully when build server is unconfigured', function () {
    config(['services.build_server.url' => null]);

    $this->artisan('build:public')
        ->expectsOutputToContain('not configured')
        ->assertExitCode(1);
});

it('build:public command fails gracefully when build server is unreachable', function () {
    config([
        'services.build_server.url' => 'http://localhost:19999',
        'services.build_server.api_key' => 'test-key',
    ]);

    $this->artisan('build:public')
        ->expectsOutputToContain('unreachable')
        ->assertExitCode(1);
});

