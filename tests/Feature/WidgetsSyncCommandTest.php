<?php

use App\Models\WidgetType;
use App\Services\WidgetRegistry;
use App\Widgets\Nav\NavDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('widgets:sync restores a stale config_schema column to match the live definition', function () {
    // Baseline: the rows a real server already has on disk.
    app(WidgetRegistry::class)->sync();

    $expected = (new NavDefinition())->toRow()['config_schema'];

    // Simulate a server whose nav row predates a pure-schema widget change —
    // the deploy path shipped a new definition but never re-synced this column.
    $nav = WidgetType::where('handle', 'nav')->firstOrFail();
    $nav->config_schema = [['key' => 'stale', 'type' => 'text']];
    $nav->save();
    expect($nav->fresh()->config_schema)->not->toEqual($expected);

    $exit = Artisan::call('widgets:sync');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('synced');

    $schemaByKey   = collect(WidgetType::where('handle', 'nav')->firstOrFail()->config_schema)->keyBy('key');
    $expectedByKey = collect($expected)->keyBy('key');
    expect($schemaByKey->keys()->all())->toEqualCanonicalizing($expectedByKey->keys()->all());
    foreach ($expectedByKey as $key => $field) {
        expect($schemaByKey[$key])->toEqualCanonicalizing($field);
    }
});

it('widgets:sync is idempotent — no duplicate rows, same id on a second run', function () {
    expect(Artisan::call('widgets:sync'))->toBe(0);

    $firstId    = WidgetType::where('handle', 'nav')->firstOrFail()->id;
    $firstCount = WidgetType::count();

    Artisan::call('widgets:sync');

    expect(WidgetType::where('handle', 'nav')->firstOrFail()->id)->toBe($firstId);
    expect(WidgetType::count())->toBe($firstCount);
    expect(WidgetType::where('handle', 'nav')->count())->toBe(1);
});
