<?php

use App\Services\WidgetRegistry;
use App\Widgets\Contracts\WidgetDefinition;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

function widgetFolder(WidgetDefinition $def): string
{
    return Str::replaceLast('Definition', '', class_basename($def));
}

it('every widget version() matches semver', function () {
    $registry = app(WidgetRegistry::class);

    foreach ($registry->all() as $def) {
        expect((bool) preg_match('/^\d+\.\d+\.\d+$/', $def->version()))
            ->toBeTrue("Widget [{$def->handle()}] has invalid version: {$def->version()}");
    }
});

it('every widget license() is in the allow-list', function () {
    $allowed = ['MIT', 'Apache-2.0', 'GPL-3.0', 'BSD-3-Clause', 'proprietary'];
    $registry = app(WidgetRegistry::class);

    foreach ($registry->all() as $def) {
        expect(in_array($def->license(), $allowed, true))->toBeTrue(
            "Widget [{$def->handle()}] has disallowed license: {$def->license()}"
        );
    }
});

it('every widget screenshot path exists on disk', function () {
    $registry = app(WidgetRegistry::class);
    expect($registry->all())->not->toBeEmpty();

    foreach ($registry->all() as $def) {
        $folder = widgetFolder($def);
        foreach ($def->screenshots() as $path) {
            $absolute = base_path('app/Widgets/' . $folder . '/' . $path);
            expect(file_exists($absolute))->toBeTrue(
                "Widget [{$def->handle()}] screenshot missing: {$path}"
            );
        }
    }
});

it('every widget keyword is a lowercase slug', function () {
    $registry = app(WidgetRegistry::class);
    expect($registry->all())->not->toBeEmpty();

    foreach ($registry->all() as $def) {
        foreach ($def->keywords() as $keyword) {
            expect((bool) preg_match('/^[a-z0-9-]+$/', (string) $keyword))->toBeTrue(
                "Widget [{$def->handle()}] has invalid keyword: {$keyword}"
            );
        }
    }
});

it('every widget preset has the required shape and references valid config keys', function () {
    $registry = app(WidgetRegistry::class);
    expect($registry->all())->not->toBeEmpty();

    foreach ($registry->all() as $def) {
        $schemaKeys = collect($def->schema())
            ->pluck('key')
            ->filter()
            ->all();

        foreach ($def->presets() as $preset) {
            $handle = $def->handle();

            expect(is_array($preset))->toBeTrue("Widget [{$handle}] has a non-array preset");

            expect(isset($preset['handle']) && is_string($preset['handle']))->toBeTrue(
                "Widget [{$handle}] preset missing string 'handle'"
            );
            expect((bool) preg_match('/^[a-z0-9-]+$/', $preset['handle']))->toBeTrue(
                "Widget [{$handle}] preset handle not a slug: {$preset['handle']}"
            );

            expect(isset($preset['label']) && is_string($preset['label']) && $preset['label'] !== '')->toBeTrue(
                "Widget [{$handle}] preset missing non-empty 'label'"
            );

            $description = array_key_exists('description', $preset) ? $preset['description'] : null;
            expect($description === null || is_string($description))->toBeTrue(
                "Widget [{$handle}] preset description must be string or null"
            );

            expect(isset($preset['config']) && is_array($preset['config']))->toBeTrue(
                "Widget [{$handle}] preset missing 'config' array"
            );
            expect(isset($preset['appearance_config']) && is_array($preset['appearance_config']))->toBeTrue(
                "Widget [{$handle}] preset missing 'appearance_config' array"
            );

            foreach (array_keys($preset['config']) as $key) {
                expect(in_array($key, $schemaKeys, true))->toBeTrue(
                    "Widget [{$handle}] preset [{$preset['handle']}] references unknown schema key: {$key}"
                );
            }
        }
    }
});

it('every widget manifest() returns the expected keys', function () {
    $expected = [
        'handle', 'label', 'description', 'category',
        'version', 'author', 'license', 'screenshots', 'keywords', 'presets',
    ];

    $registry = app(WidgetRegistry::class);

    foreach ($registry->all() as $def) {
        $manifest = $def->manifest();
        $keys = array_keys($manifest);
        sort($keys);
        $sortedExpected = $expected;
        sort($sortedExpected);
        expect($keys)->toBe(
            $sortedExpected,
            "Widget [{$def->handle()}] manifest() has unexpected keys"
        );
    }
});
