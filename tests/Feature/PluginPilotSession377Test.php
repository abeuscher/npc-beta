<?php

use App\Models\WidgetType;
use App\Providers\WidgetServiceProvider;
use App\Services\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\LogoGarden\LogoGardenDefinition;
use Plugins\LogoGarden\LogoGardenServiceProvider;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Session 377 (Plugin Architecture arc P1): the pilot widget is registered
// only via its own service provider through config/plugins.php. These tests
// pin the registration seam; render parity stays pinned by
// LogoGardenContractRetrofitTest / LogoGardenWidgetTest.

it('registers logo_garden through the plugin provider list', function () {
    $def = app(WidgetRegistry::class)->find('logo_garden');

    expect($def)->toBeInstanceOf(LogoGardenDefinition::class);
    expect(config('plugins'))->toContain(LogoGardenServiceProvider::class);
    expect(app()->providerIsLoaded(LogoGardenServiceProvider::class))->toBeTrue();
})->group('widget-lint');

it('the plugin provider alone registers logo_garden into a fresh registry', function () {
    $original = app(WidgetRegistry::class);

    $fresh = new WidgetRegistry();
    app()->instance(WidgetRegistry::class, $fresh);

    try {
        (new LogoGardenServiceProvider(app()))->boot();
        expect($fresh->find('logo_garden'))->toBeInstanceOf(LogoGardenDefinition::class);
        expect($fresh->all())->toHaveCount(1);
    } finally {
        app()->instance(WidgetRegistry::class, $original);
    }
})->group('widget-lint');

it('core WidgetServiceProvider no longer references the pilot widget', function () {
    $source = file_get_contents((new ReflectionClass(WidgetServiceProvider::class))->getFileName());

    expect($source)->not->toContain('LogoGarden');
})->group('widget-lint');

it('resolves the pilot template via its own view namespace, not widgets::', function () {
    expect(view()->exists('plugin-logo-garden::template'))->toBeTrue();
    expect(view()->exists('widgets::LogoGarden.template'))->toBeFalse();
})->group('widget-lint');

it('syncs the pilot row with plugin-relative template and asset paths', function () {
    app(WidgetRegistry::class)->sync();

    $row = WidgetType::where('handle', 'logo_garden')->firstOrFail();

    expect($row->template)->toBe("@include('plugin-logo-garden::template')");
    expect($row->assets['scss'])->toBe(['vendor/nonprofitcrm/logo-garden/styles.scss']);
    expect($row->assets['js'])->toBe(['vendor/nonprofitcrm/logo-garden/script.js']);
    expect($row->assets['libs'])->toBe(['swiper']);

    foreach (array_merge($row->assets['scss'], $row->assets['js']) as $path) {
        expect(file_exists(base_path($path)))->toBeTrue("Declared asset missing on disk: {$path}");
    }
})->group('widget-lint');
