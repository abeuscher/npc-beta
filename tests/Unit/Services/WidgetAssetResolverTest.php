<?php

use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetAssetResolver;

function writeTempManifest(array $payload): string
{
    $path = sys_get_temp_dir() . '/widget-asset-resolver-' . uniqid('', true) . '.json';
    file_put_contents($path, json_encode($payload));
    return $path;
}

it('returns an empty manifest when the file does not exist', function () {
    $resolver = new WidgetAssetResolver('/nonexistent/path/manifest.json');

    expect($resolver->manifest())->toBe([])
        ->and($resolver->widgetCss())->toBeNull()
        ->and($resolver->widgetJs())->toBeNull()
        ->and($resolver->allLibs())->toBe([]);
});

it('returns decoded manifest contents and memoizes reads', function () {
    $path = writeTempManifest([
        'css'  => 'public-widgets-abc.css',
        'js'   => 'public-widgets-abc.js',
        'libs' => [
            'swiper'  => ['css' => '/build/libs/swiper.css', 'js' => '/build/libs/swiper.js'],
            'chart.js' => ['js' => '/build/libs/chartjs.js'],
        ],
    ]);

    $resolver = new WidgetAssetResolver($path);
    $first  = $resolver->manifest();
    unlink($path);
    $second = $resolver->manifest();

    expect($first)->toBe($second)
        ->and($first['css'])->toBe('public-widgets-abc.css');
});

it('widgetCss and widgetJs prefix manifest filenames with the public bundle path', function () {
    $path = writeTempManifest([
        'css' => 'public-widgets-xyz.css',
        'js'  => 'public-widgets-xyz.js',
    ]);

    $resolver = new WidgetAssetResolver($path);

    expect($resolver->widgetCss())->toBe('/build/widgets/public-widgets-xyz.css')
        ->and($resolver->widgetJs())->toBe('/build/widgets/public-widgets-xyz.js');

    unlink($path);
});

it('widgetCss and widgetJs return null when manifest entries are missing or empty', function () {
    $path = writeTempManifest(['css' => '']);

    $resolver = new WidgetAssetResolver($path);

    expect($resolver->widgetCss())->toBeNull()
        ->and($resolver->widgetJs())->toBeNull();

    unlink($path);
});

it('allLibs returns every lib entry in the manifest', function () {
    $path = writeTempManifest([
        'libs' => [
            'swiper'  => ['js' => '/build/libs/swiper.js'],
            'chart.js' => ['js' => '/build/libs/chartjs.js'],
        ],
    ]);

    $resolver = new WidgetAssetResolver($path);

    expect($resolver->allLibs())
        ->toHaveKey('swiper')
        ->toHaveKey('chart.js')
        ->and($resolver->allLibs()['swiper']['js'])->toBe('/build/libs/swiper.js');

    unlink($path);
});

it('libs resolves requested handles and silently drops unknown handles (fail-closed)', function () {
    $path = writeTempManifest([
        'libs' => [
            'swiper'  => ['js' => '/build/libs/swiper.js'],
            'chart.js' => ['js' => '/build/libs/chartjs.js'],
        ],
    ]);

    $resolver = new WidgetAssetResolver($path);

    $resolved = $resolver->libs(['swiper', 'unknown-lib']);

    expect($resolved)
        ->toHaveKey('swiper')
        ->not->toHaveKey('unknown-lib');

    unlink($path);
});

it('libsForWidgets walks WidgetType assets column, dedupes, resolves through the manifest', function () {
    $path = writeTempManifest([
        'libs' => [
            'swiper'   => ['js' => '/build/libs/swiper.js'],
            'chart.js' => ['js' => '/build/libs/chartjs.js'],
            'jcalendar' => ['js' => '/build/libs/jcalendar.js'],
        ],
    ]);

    $resolver = new WidgetAssetResolver($path);

    $carousel = new WidgetType();
    $carousel->assets = ['libs' => ['swiper']];

    $bar = new WidgetType();
    $bar->assets = ['libs' => ['chart.js']];

    $blogListing = new WidgetType();
    $blogListing->assets = ['libs' => ['swiper']]; // duplicate of carousel's

    $noLibs = new WidgetType();
    $noLibs->assets = [];

    $resolved = $resolver->libsForWidgets([$carousel, $bar, $blogListing, $noLibs]);

    expect(array_keys($resolved))->toBe(['swiper', 'chart.js'])
        ->and($resolved['swiper']['js'])->toBe('/build/libs/swiper.js');

    unlink($path);
});

it('libsForWidgets accepts PageWidget instances and resolves via widgetType relation', function () {
    $path = writeTempManifest([
        'libs' => [
            'swiper' => ['js' => '/build/libs/swiper.js'],
        ],
    ]);

    $resolver = new WidgetAssetResolver($path);

    $widgetType = new WidgetType();
    $widgetType->assets = ['libs' => ['swiper']];

    $pw = new PageWidget();
    $pw->setRelation('widgetType', $widgetType);

    expect($resolver->libsForWidgets([$pw]))->toHaveKey('swiper');

    unlink($path);
});
